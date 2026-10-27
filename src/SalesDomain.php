<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales;

use Obelaw\Ium\Abstracts\Domain;
use Obelaw\Ium\Engine\ObelawConfigManager;
use Obelaw\Ium\Sales\Services\AclService;
use Obelaw\Ium\Sales\Services\AuditService;
use Obelaw\Ium\Sales\Services\ChannelService;
use Obelaw\Ium\Sales\Services\FulfillmentService;
use Obelaw\Ium\Sales\Services\OrderService;
use Obelaw\Ium\Sales\Services\PricingEngineService;
use Obelaw\Ium\Sales\Services\QuotationService;
use Obelaw\Ium\Sales\Services\RmaService;

/**
 * Sales & Order Management System (OMS) Domain Gateway for Obelawium.
 *
 * Implements the fluent chain: ium()->sales()->{service}()->method()
 */
final class SalesDomain extends Domain
{
    public function __construct(
        ObelawConfigManager $config,
        private readonly ?SalesManager $manager = null
    ) {
        parent::__construct($config);
    }

    private function getManager(): SalesManager
    {
        return $this->manager ?? app(SalesManager::class);
    }

    /**
     * Master sales order ingestion and lifecycle service.
     */
    public function orders(): OrderService
    {
        return $this->getManager()->orders();
    }

    /**
     * Commercial quotation and proforma proposal service.
     */
    public function quotations(): QuotationService
    {
        return $this->getManager()->quotations();
    }

    /**
     * Stock allocation, routing, and multi-batch split fulfillment service.
     */
    public function fulfillment(): FulfillmentService
    {
        return $this->getManager()->fulfillment();
    }

    /**
     * Reverse logistics & Return Merchandise Authorization (RMA) service.
     */
    public function rmas(): RmaService
    {
        return $this->getManager()->rmas();
    }

    /**
     * Omnichannel commercial channel profiles and routing policy service.
     */
    public function channels(): ChannelService
    {
        return $this->getManager()->channels();
    }

    /**
     * Commercial pricing, taxes, discounts, and financial balance engine.
     */
    public function pricing(): PricingEngineService
    {
        return $this->getManager()->pricing();
    }

    /**
     * Append-only forensic commercial audit trail service.
     */
    public function audit(): AuditService
    {
        return $this->getManager()->audit();
    }

    /**
     * Anti-Corruption Layer (ACL) for inbound/outbound cross-domain contracts.
     */
    public function acl(): AclService
    {
        return $this->getManager()->acl();
    }
}
