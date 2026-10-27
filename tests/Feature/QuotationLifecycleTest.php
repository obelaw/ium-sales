<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Obelaw\Ium\Sales\Data\CreateQuotationDto;
use Obelaw\Ium\Sales\Data\QuotationLineDto;
use Obelaw\Ium\Sales\Enums\OrderStatus;
use Obelaw\Ium\Sales\Enums\QuotationStatus;
use Obelaw\Ium\Sales\Events\QuotationAccepted;

it('completes the quotation lifecycle and converts into a confirmed sales order', function () {
    Event::fake([QuotationAccepted::class]);

    $dto = new CreateQuotationDto(
        lines: [
            new QuotationLineDto(
                sku: 'PROD-001',
                name: 'Enterprise Server Rack',
                unitPrice: 1200.0,
                quantity: 2.0,
                discountAmount: 100.0,
                taxRate: 10.0,
            ),
        ],
        customerType: 'customer',
        customerId: 42,
        channelCode: 'b2b-portal',
        currency: 'USD',
        shippingTotal: 50.0,
    );

    // 1. Create draft quotation
    $quotation = ium()->sales()->quotations()->create($dto);
    expect($quotation->status)->toBe(QuotationStatus::Draft);
    expect($quotation->subtotal)->toBe(2400.0);
    expect($quotation->discount_total)->toBe(100.0);
    // Taxable: 2300 * 10% = 230
    expect($quotation->tax_total)->toBe(230.0);
    expect($quotation->shipping_total)->toBe(50.0);
    // Grand total: 2300 + 230 + 50 = 2580.0
    expect($quotation->grand_total)->toBe(2580.0);

    // 2. Send quotation
    $quotation = ium()->sales()->quotations()->send($quotation);
    expect($quotation->status)->toBe(QuotationStatus::Sent);

    // 3. Customer acceptance
    $quotation = ium()->sales()->quotations()->accept($quotation);
    expect($quotation->status)->toBe(QuotationStatus::Accepted);
    Event::assertDispatched(QuotationAccepted::class);

    // 4. Convert to sales order
    $order = ium()->sales()->quotations()->convertToOrder($quotation);
    expect($order)->toBeInstanceOf(\Obelaw\Ium\Sales\Models\SalesOrder::class);
    expect($order->status)->toBe(OrderStatus::Draft);
    expect($order->grand_total)->toBe(2580.0);
    expect($order->quotation_id)->toBe($quotation->id);
    expect($quotation->fresh()->status)->toBe(QuotationStatus::Converted);
});
