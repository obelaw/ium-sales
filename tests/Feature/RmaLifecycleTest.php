<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Obelaw\Ium\Sales\Data\CreateRmaDto;
use Obelaw\Ium\Sales\Data\CreateSalesOrderDto;
use Obelaw\Ium\Sales\Data\MarkShippedDto;
use Obelaw\Ium\Sales\Data\ReceiveRmaGoodsDto;
use Obelaw\Ium\Sales\Data\RmaLineDto;
use Obelaw\Ium\Sales\Data\SalesOrderLineDto;
use Obelaw\Ium\Sales\Enums\RmaDisposition;
use Obelaw\Ium\Sales\Enums\RmaStatus;
use Obelaw\Ium\Sales\Events\ReturnAuthorized;
use Obelaw\Ium\Sales\Exceptions\InvalidRmaQuantityException;

it('orchestrates the complete RMA return lifecycle and restock disposition', function () {
    Event::fake([ReturnAuthorized::class]);

    // 1. Create and fulfill an order
    $dto = new CreateSalesOrderDto(
        lines: [
            new SalesOrderLineDto(sku: 'RETURN-ITEM', name: 'Returnable Device', unitPrice: 300.0, quantity: 3.0),
        ],
    );

    $order = ium()->sales()->orders()->create($dto);
    $order = ium()->sales()->orders()->confirm($order);
    $order = ium()->sales()->fulfillment()->allocate($order);
    $batch = $order->batches()->first();
    $batch = ium()->sales()->fulfillment()->releaseBatch($batch);
    ium()->sales()->fulfillment()->markBatchShipped($batch, new MarkShippedDto());

    $line = $order->lines()->first();
    expect($line->shipped_quantity)->toBe(3.0);

    // 2. Reject RMA if requested quantity exceeds shipped quantity
    expect(fn() => ium()->sales()->rmas()->request(new CreateRmaDto(
        salesOrderId: $order->id,
        reasonCode: 'DEFECTIVE',
        lines: [new RmaLineDto(salesOrderLineId: $line->id, quantityRequested: 5.0)],
    )))->toThrow(InvalidRmaQuantityException::class);

    // 3. Request valid RMA for 2 units
    $rma = ium()->sales()->rmas()->request(new CreateRmaDto(
        salesOrderId: $order->id,
        reasonCode: 'DEFECTIVE',
        lines: [new RmaLineDto(salesOrderLineId: $line->id, quantityRequested: 2.0)],
    ));
    expect($rma->status)->toBe(RmaStatus::PendingAuthorization);
    expect($rma->lines)->toHaveCount(1);

    // 4. Authorize RMA
    $rma = ium()->sales()->rmas()->authorize($rma);
    expect($rma->status)->toBe(RmaStatus::Authorized);
    Event::assertDispatched(ReturnAuthorized::class);

    // 5. Receive and inspect returned goods
    $rmaLine = $rma->lines()->first();
    $rma = ium()->sales()->rmas()->receiveGoods($rma, new ReceiveRmaGoodsDto(
        items: [
            [
                'rma_line_id' => $rmaLine->id,
                'quantity_received' => 2.0,
                'disposition' => RmaDisposition::Restock->value,
            ],
        ]
    ));
    expect($rma->status)->toBe(RmaStatus::Received);

    // 6. Approve credit note compensation
    $rma = ium()->sales()->rmas()->approveCredit($rma);
    expect($rma->status)->toBe(RmaStatus::ApprovedForCredit);

    // 7. Verify line returned_quantity is updated
    expect($line->fresh()->returned_quantity)->toBe(2.0);
    expect($line->fresh()->returnableQuantity())->toBe(1.0);

    // 8. Close RMA
    $rma = ium()->sales()->rmas()->close($rma);
    expect($rma->status)->toBe(RmaStatus::Closed);
    expect($rma->closed_at)->not->toBeNull();
});
