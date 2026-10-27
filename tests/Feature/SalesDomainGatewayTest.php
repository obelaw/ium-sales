<?php

declare(strict_types=1);

use Obelaw\Ium\Sales\Models\SalesChannel;
use Obelaw\Ium\Sales\Models\SalesOrder;
use Obelaw\Ium\Sales\SalesDomain;

it('resolves sales domain gateway via ium() fluent helper', function () {
    expect(ium()->hasDomain('sales'))->toBeTrue();
    expect(ium()->sales())->toBeInstanceOf(SalesDomain::class);
});

it('resolves all domain services from sales domain gateway', function () {
    $sales = ium()->sales();

    expect($sales->orders())->toBeInstanceOf(\Obelaw\Ium\Sales\Services\OrderService::class);
    expect($sales->quotations())->toBeInstanceOf(\Obelaw\Ium\Sales\Services\QuotationService::class);
    expect($sales->fulfillment())->toBeInstanceOf(\Obelaw\Ium\Sales\Services\FulfillmentService::class);
    expect($sales->rmas())->toBeInstanceOf(\Obelaw\Ium\Sales\Services\RmaService::class);
    expect($sales->channels())->toBeInstanceOf(\Obelaw\Ium\Sales\Services\ChannelService::class);
    expect($sales->pricing())->toBeInstanceOf(\Obelaw\Ium\Sales\Services\PricingEngineService::class);
    expect($sales->audit())->toBeInstanceOf(\Obelaw\Ium\Sales\Services\AuditService::class);
    expect($sales->acl())->toBeInstanceOf(\Obelaw\Ium\Sales\Services\AclService::class);
});

it('applies the ium_sales_ physical table prefix correctly', function () {
    $channel = new SalesChannel();
    expect($channel->getTable())->toBe('ium_sales_channels');

    $order = new SalesOrder();
    expect($order->getTable())->toBe('ium_sales_orders');
});
