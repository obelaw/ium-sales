<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Obelaw\Ium\Sales\Data\CreateSalesOrderDto;
use Obelaw\Ium\Sales\Data\SalesOrderLineDto;
use Obelaw\Ium\Sales\Enums\OrderStatus;
use Obelaw\Ium\Sales\Events\OrderCancelled;
use Obelaw\Ium\Sales\Exceptions\CannotCancelReleasedOrderException;

it('permits order cancellation before fulfillment release and blocks it after release', function () {
    Event::fake([OrderCancelled::class]);

    $dto = new CreateSalesOrderDto(
        lines: [
            new SalesOrderLineDto(sku: 'SKU-CANCEL', name: 'Cancellable Item', unitPrice: 75.0, quantity: 1.0),
        ],
    );

    // Case 1: Pre-release cancellation on Confirmed order is permitted
    $order1 = ium()->sales()->orders()->create($dto);
    $order1 = ium()->sales()->orders()->confirm($order1);

    $cancelled1 = ium()->sales()->orders()->cancel($order1, 'Customer requested immediate cancellation.');
    expect($cancelled1->status)->toBe(OrderStatus::Cancelled);
    expect($cancelled1->cancelled_at)->not->toBeNull();
    expect($cancelled1->cancellation_reason)->toBe('Customer requested immediate cancellation.');
    Event::assertDispatched(OrderCancelled::class);

    // Case 2: Post-release cancellation is strictly forbidden under Domain Invariant 4
    $order2 = ium()->sales()->orders()->create($dto);
    $order2 = ium()->sales()->orders()->confirm($order2);
    $order2 = ium()->sales()->fulfillment()->allocate($order2);
    $batch = $order2->batches()->first();
    ium()->sales()->fulfillment()->releaseBatch($batch);

    expect($order2->fresh()->status)->toBe(OrderStatus::Released);

    // Attempt to cancel released order must fail
    expect(fn() => ium()->sales()->orders()->cancel($order2->fresh(), 'Cancel after release'))
        ->toThrow(CannotCancelReleasedOrderException::class);
});
