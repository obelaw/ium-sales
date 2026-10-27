<?php

declare(strict_types=1);

use Obelaw\Ium\Sales\Data\CreateChannelDto;
use Obelaw\Ium\Sales\Data\IngestOrderDto;
use Obelaw\Ium\Sales\Data\SalesOrderLineDto;
use Obelaw\Ium\Sales\Enums\ChannelType;

it('ingests omnichannel orders through the ACL and exports domain integration payloads', function () {
    // 1. Register a channel profile
    $channel = ium()->sales()->channels()->register(new CreateChannelDto(
        code: 'shopify-d2c',
        name: 'Shopify Storefront',
        type: ChannelType::Ecommerce->value,
        currency: 'USD',
    ));
    expect($channel->code)->toBe('shopify-d2c');

    // 2. Ingest external channel order
    $dto = new IngestOrderDto(
        channelCode: 'shopify-d2c',
        channelOrderId: 'EXT-10992',
        customerEmail: 'buyer@example.com',
        customerName: 'Alice Smith',
        lines: [
            new SalesOrderLineDto(
                sku: 'SHIRT-BLU',
                name: 'Blue Oxford Shirt',
                unitPrice: 45.0,
                quantity: 2.0,
            ),
        ],
        shippingTotal: 5.0,
    );

    $order1 = ium()->sales()->orders()->ingest($dto);
    expect($order1->channel_code)->toBe('shopify-d2c');
    expect($order1->grand_total)->toBe(95.0);

    // 3. Idempotent re-ingestion returns existing order without duplicates
    $order2 = ium()->sales()->orders()->ingest($dto);
    expect($order2->id)->toBe($order1->id);

    // 4. Test ACL export contracts
    $fmsPayload = ium()->sales()->acl()->exportToFmsPayload($order1);
    expect($fmsPayload['document_type'])->toBe('CUSTOMER_INVOICE');
    expect($fmsPayload['grand_total'])->toBe(95.0);
    expect($fmsPayload['lines'])->toHaveCount(1);
});
