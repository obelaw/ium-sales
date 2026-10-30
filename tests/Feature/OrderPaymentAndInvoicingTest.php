<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Obelaw\Ium\Sales\Data\CreateSalesOrderDto;
use Obelaw\Ium\Sales\Data\RecordOrderPaymentDto;
use Obelaw\Ium\Sales\Data\SalesOrderLineDto;
use Obelaw\Ium\Sales\Events\OrderInvoiced;
use Obelaw\Ium\Sales\Events\OrderPaymentRecorded;
use Obelaw\Ium\Sales\Models\SalesOrder;

it('orchestrates commercial invoicing and partial-to-full payment collection', function () {
    Event::fake([OrderInvoiced::class, OrderPaymentRecorded::class]);

    $order = ium()->sales()->orders()->create(CreateSalesOrderDto::from([
        'order_number' => 'SO-PAY-001',
        'customer_id' => 'CUST-001',
        'currency' => 'USD',
        'lines' => [
            [
                'sku' => 'LAPTOP-PRO',
                'name' => 'Workstation Pro',
                'unit_price' => 1000.0,
                'quantity' => 2.0,
                'discount_amount' => 100.0,
                'tax_rate' => 10.0,
            ],
        ],
        'shipping_total' => 50.0,
    ]));

    // subtotal = 2000, discount = 100, taxable = 1900, tax = 190, shipping = 50, grand_total = 2140
    expect($order->grand_total)->toEqual(2140.0);
    expect($order->grand_total_minor)->toBe(214000);
    expect($order->subtotal_minor)->toBe(200000);
    expect($order->tax_total_minor)->toBe(19000);
    expect($order->discount_total_minor)->toBe(10000);
    expect($order->shipping_total_minor)->toBe(5000);

    // Issue invoice
    $invoiceNumber = ium()->sales()->payments()->issueInvoice($order);
    expect($invoiceNumber)->toStartWith('INV-');
    Event::assertDispatched(OrderInvoiced::class, fn($e) => $e->invoiceNumber === $invoiceNumber);

    // Re-issuing returns existing invoice number idempotently
    expect(ium()->sales()->payments()->issueInvoice($order))->toBe($invoiceNumber);

    // Confirm order
    $order = ium()->sales()->orders()->confirm($order);
    expect($order->payment_status)->toBe('pending');

    // Record partial deposit payment ($1000)
    $payment1 = ium()->sales()->payments()->recordPayment(RecordOrderPaymentDto::from([
        'sales_order_id' => $order->id,
        'amount' => 1000.0,
        'payment_method' => 'bank_wire',
        'notes' => '50% initial deposit',
    ]));

    expect($payment1->amount)->toEqual(1000.0);
    expect($payment1->amount_minor)->toBe(100000);

    $order->refresh();
    expect($order->payment_status)->toBe('partially_paid');
    expect($order->totalPaid())->toEqual(1000.0);
    expect($order->totalPaidMinor())->toBe(100000);

    Event::assertDispatched(OrderPaymentRecorded::class, fn($e) => $e->payment->id === $payment1->id);

    // Record final settlement payment ($1140)
    $payment2 = ium()->sales()->payments()->recordPayment(RecordOrderPaymentDto::from([
        'sales_order_id' => $order->id,
        'amount' => 1140.0,
        'payment_method' => 'credit_card',
        'notes' => 'Settlement balance',
    ]));

    $order->refresh();
    expect($order->payment_status)->toBe('paid');
    expect($order->totalPaid())->toEqual(2140.0);
    expect($order->totalPaidMinor())->toBe(214000);

    // Retrieve payment collection
    $payments = ium()->sales()->payments()->getPaymentsForOrder($order->id);
    expect($payments)->toHaveCount(2);
});
