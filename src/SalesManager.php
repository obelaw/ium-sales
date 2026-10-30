<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales;

use Obelaw\Ium\Sales\Services\AclService;
use Obelaw\Ium\Sales\Services\AuditService;
use Obelaw\Ium\Sales\Services\ChannelService;
use Obelaw\Ium\Sales\Services\FulfillmentService;
use Obelaw\Ium\Sales\Services\OrderService;
use Obelaw\Ium\Sales\Services\PaymentService;
use Obelaw\Ium\Sales\Services\PricingEngineService;
use Obelaw\Ium\Sales\Services\QuotationService;
use Obelaw\Ium\Sales\Services\RmaService;

/**
 * Service resolver for the Sales & OMS domain.
 */
final class SalesManager
{
    public function orders(): OrderService
    {
        return app(OrderService::class);
    }

    public function quotations(): QuotationService
    {
        return app(QuotationService::class);
    }

    public function fulfillment(): FulfillmentService
    {
        return app(FulfillmentService::class);
    }

    public function rmas(): RmaService
    {
        return app(RmaService::class);
    }

    public function channels(): ChannelService
    {
        return app(ChannelService::class);
    }

    public function pricing(): PricingEngineService
    {
        return app(PricingEngineService::class);
    }

    public function payments(): PaymentService
    {
        return app(PaymentService::class);
    }

    public function audit(): AuditService
    {
        return app(AuditService::class);
    }

    public function acl(): AclService
    {
        return app(AclService::class);
    }
}
