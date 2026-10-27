<?php

declare(strict_types=1);

use Obelaw\Ium\Sales\Data\CreateSalesOrderDto;
use Obelaw\Ium\Sales\Data\SalesOrderLineDto;
use Obelaw\Ium\Sales\Exceptions\OrderPriceLockedException;

it('strictly enforces the Price-Lock Law on confirmed sales orders', function () {
    $dto = new CreateSalesOrderDto(
        lines: [
            new SalesOrderLineDto(
                sku: 'LOCKED-SKU',
                name: 'Locked Commodity',
                unitPrice: 200.0,
                quantity: 1.0,
            ),
        ],
    );

    $order = ium()->sales()->orders()->create($dto);
    $order = ium()->sales()->orders()->confirm($order);

    // 1. Attempting to modify grand_total on confirmed order must throw OrderPriceLockedException
    expect(fn() => $order->update(['grand_total' => 150.0]))
        ->toThrow(OrderPriceLockedException::class);

    // 2. Attempting to modify currency on confirmed order must throw OrderPriceLockedException
    expect(fn() => $order->update(['currency' => 'EUR']))
        ->toThrow(OrderPriceLockedException::class);

    // 3. Attempting to delete a confirmed order must throw OrderPriceLockedException
    expect(fn() => $order->delete())
        ->toThrow(OrderPriceLockedException::class);

    // 4. Attempting to alter line pricing on a confirmed order must throw OrderPriceLockedException
    $line = $order->lines()->first();
    expect(fn() => $line->update(['unit_price' => 250.0]))
        ->toThrow(OrderPriceLockedException::class);

    // 5. Attempting to delete lines from a confirmed order must throw OrderPriceLockedException
    expect(fn() => $line->delete())
        ->toThrow(OrderPriceLockedException::class);
});
