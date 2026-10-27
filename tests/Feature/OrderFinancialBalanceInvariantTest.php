<?php

declare(strict_types=1);

use Obelaw\Ium\Sales\Data\CreateSalesOrderDto;
use Obelaw\Ium\Sales\Data\SalesOrderLineDto;
use Obelaw\Ium\Sales\Enums\OrderStatus;
use Obelaw\Ium\Sales\Exceptions\InvalidFinancialBalanceException;
use Obelaw\Ium\Sales\Models\SalesOrder;

it('validates financial balance rule upon order confirmation', function () {
    $dto = new CreateSalesOrderDto(
        lines: [
            new SalesOrderLineDto(
                sku: 'SKU-A',
                name: 'Item Alpha',
                unitPrice: 100.0,
                quantity: 2.0,
                discountAmount: 20.0,
                taxRate: 15.0,
            ),
        ],
        shippingTotal: 10.0,
    );

    $order = ium()->sales()->orders()->create($dto);
    expect($order->status)->toBe(OrderStatus::Draft);

    // Confirm order successfully satisfies the balance equation
    $confirmed = ium()->sales()->orders()->confirm($order);
    expect($confirmed->status)->toBe(OrderStatus::Confirmed);
    expect($confirmed->confirmed_at)->not->toBeNull();
});

it('aborts order confirmation if financial balance rule is corrupted', function () {
    $dto = new CreateSalesOrderDto(
        lines: [
            new SalesOrderLineDto(
                sku: 'SKU-B',
                name: 'Item Beta',
                unitPrice: 50.0,
                quantity: 1.0,
            ),
        ],
    );

    $order = ium()->sales()->orders()->create($dto);

    // Artificially tamper with grand_total in database
    SalesOrder::where('id', $order->id)->update(['grand_total' => 999.00]);

    $corruptedOrder = SalesOrder::find($order->id);

    expect(fn() => ium()->sales()->orders()->confirm($corruptedOrder))
        ->toThrow(InvalidFinancialBalanceException::class);
});
