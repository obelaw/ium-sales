<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Obelaw\Ium\Sales\Data\CreateSalesOrderDto;
use Obelaw\Ium\Sales\Data\MarkShippedDto;
use Obelaw\Ium\Sales\Data\SalesOrderLineDto;
use Obelaw\Ium\Sales\Data\SplitBatchDto;
use Obelaw\Ium\Sales\Data\SplitOrderDto;
use Obelaw\Ium\Sales\Enums\FulfillmentBatchStatus;
use Obelaw\Ium\Sales\Enums\OrderStatus;
use Obelaw\Ium\Sales\Events\FulfillmentBatchReleased;
use Obelaw\Ium\Sales\Events\OrderSplitted;
use Obelaw\Ium\Sales\Exceptions\InsufficientInventoryException;

it('orchestrates stock allocation, split shipments across sourcing nodes, and dispatch tracking', function () {
    Event::fake([OrderSplitted::class, FulfillmentBatchReleased::class]);

    $dto = new CreateSalesOrderDto(
        lines: [
            new SalesOrderLineDto(sku: 'SKU-NORTH', name: 'Item North', unitPrice: 100.0, quantity: 2.0),
            new SalesOrderLineDto(sku: 'SKU-SOUTH', name: 'Item South', unitPrice: 200.0, quantity: 1.0),
        ],
    );

    $order = ium()->sales()->orders()->create($dto);
    $order = ium()->sales()->orders()->confirm($order);

    // 1. Check ATP allocation failure if stock is insufficient
    expect(fn() => ium()->sales()->fulfillment()->allocate($order, 'WH-NORTH', ['SKU-NORTH' => 1.0, 'SKU-SOUTH' => 5.0]))
        ->toThrow(InsufficientInventoryException::class);

    // 2. Successful stock allocation
    $order = ium()->sales()->fulfillment()->allocate($order, 'WH-NORTH', ['SKU-NORTH' => 10.0, 'SKU-SOUTH' => 10.0]);
    expect($order->status)->toBe(OrderStatus::Allocated);

    // 3. Split order across two sourcing nodes (Warehouse North and Store South)
    $lineNorth = $order->lines()->where('sku', 'SKU-NORTH')->first();
    $lineSouth = $order->lines()->where('sku', 'SKU-SOUTH')->first();

    $splitDto = new SplitOrderDto(batches: [
        new SplitBatchDto(
            sourcingNodeId: 'WH-NORTH',
            items: [['order_line_id' => $lineNorth->id, 'quantity' => 2.0]],
            carrierCode: 'FEDEX',
        ),
        new SplitBatchDto(
            sourcingNodeId: 'STORE-SOUTH',
            items: [['order_line_id' => $lineSouth->id, 'quantity' => 1.0]],
            carrierCode: 'DHL',
        ),
    ]);

    $batches = ium()->sales()->fulfillment()->split($order, $splitDto);
    expect($batches)->toHaveCount(2);
    Event::assertDispatched(OrderSplitted::class);

    // 4. Release first batch to WMS
    $batch1 = $batches->first();
    $batch1 = ium()->sales()->fulfillment()->releaseBatch($batch1);
    expect($batch1->status)->toBe(FulfillmentBatchStatus::Released);
    expect($order->fresh()->status)->toBe(OrderStatus::Released);
    Event::assertDispatched(FulfillmentBatchReleased::class);

    // 5. Mark first batch shipped
    $batch1 = ium()->sales()->fulfillment()->markBatchShipped($batch1, new MarkShippedDto(
        carrierCode: 'FEDEX',
        trackingNumber: 'FDX-123456789'
    ));
    expect($batch1->status)->toBe(FulfillmentBatchStatus::Shipped);
    expect($lineNorth->fresh()->shipped_quantity)->toBe(2.0);

    // 6. Release and deliver remaining batch
    $batch2 = $batches->last();
    $batch2 = ium()->sales()->fulfillment()->releaseBatch($batch2);
    $batch2 = ium()->sales()->fulfillment()->markBatchShipped($batch2, new MarkShippedDto(
        carrierCode: 'DHL',
        trackingNumber: 'DHL-987654321'
    ));
    $batch1 = ium()->sales()->fulfillment()->markBatchDelivered($batch1);
    $batch2 = ium()->sales()->fulfillment()->markBatchDelivered($batch2);

    expect($batch1->status)->toBe(FulfillmentBatchStatus::Delivered);
    expect($batch2->status)->toBe(FulfillmentBatchStatus::Delivered);
    expect($order->fresh()->status)->toBe(OrderStatus::Completed);
});
