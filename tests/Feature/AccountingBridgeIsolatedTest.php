<?php

declare(strict_types=1);

use Obelaw\Ium\ObelawiumManager;
use Obelaw\Ium\Sales\Data\CreateSalesOrderDto;
use Obelaw\Ium\Sales\Data\RecordOrderPaymentDto;
use Obelaw\Ium\Sales\Enums\SalesConfigEnum;
use Obelaw\Ium\Sales\Integrations\Accounting\SalesAccountingBridge;
use Obelaw\Ium\Sales\SalesDomain;

beforeEach(function () {
    // Ensure accounting is not registered in isolated tests
    $mgr = ObelawiumManager::getInstance();
    $reflection = new ReflectionClass($mgr);
    $prop = $reflection->getProperty('domains');
    $prop->setAccessible(true);
    $domains = $prop->getValue($mgr);
    unset($domains['accounting']);
    $prop->setValue($mgr, $domains);
});

it('remains completely inert and silent when accounting domain is absent or disabled', function () {
    $bridge = app(SalesAccountingBridge::class);

    // Default when accounting domain is not registered
    expect($bridge->isAvailable())->toBeFalse();

    // Confirming an order should NOT throw any errors even with accounting listeners active
    $order = ium()->sales()->orders()->create(CreateSalesOrderDto::from([
        'order_number' => 'SO-ISOLATED-001',
        'customer_id' => 'CUST-NO-ACC',
        'lines' => [
            [
                'sku' => 'ITEM-01',
                'name' => 'Item 01',
                'unit_price' => 50.0,
                'quantity' => 1.0,
            ],
        ],
    ]));

    $order = ium()->sales()->orders()->confirm($order);
    expect($order->status->value)->toBe('confirmed');
    expect($order->metadata['accounting_journal_entry_id'] ?? null)->toBeNull();

    // Recording payment also succeeds cleanly
    $payment = ium()->sales()->payments()->recordPayment(RecordOrderPaymentDto::from([
        'sales_order_id' => $order->id,
        'amount' => 50.0,
    ]));

    expect($payment->status)->toBe('completed');
    expect($payment->metadata['accounting_journal_entry_id'] ?? null)->toBeNull();

    // Cancelling also executes cleanly without errors
    $order = ium()->sales()->orders()->cancel($order, 'Customer requested cancellation');
    expect($order->status->value)->toBe('cancelled');
});

it('strictly honors the config toggle when explicitly disabled', function () {
    ium_set_config(SalesConfigEnum::AccountingEnabled, false);
    $bridge = app(SalesAccountingBridge::class);

    expect($bridge->isAvailable())->toBeFalse();
});
