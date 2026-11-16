<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales;

use Illuminate\Support\Facades\Artisan;
use Obelaw\Ium\Abstracts\Domain;
use Obelaw\Ium\Engine\ObelawConfigManager;
use Obelaw\Ium\Sales\Providers\ObelawSalesServiceProvider;
use Obelaw\Ium\Sales\Services\AclService;
use Obelaw\Ium\Sales\Services\AuditService;
use Obelaw\Ium\Sales\Services\ChannelService;
use Obelaw\Ium\Sales\Services\CustomerService;
use Obelaw\Ium\Sales\Services\FulfillmentService;
use Obelaw\Ium\Sales\Services\OrderService;
use Obelaw\Ium\Sales\Services\PaymentService;
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
        private readonly ObelawConfigManager $config,
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
     * Customer accounts, contacts, and commercial profiles directory service.
     */
    public function customers(): CustomerService
    {
        return $this->getManager()->customers();
    }

    /**
     * Commercial pricing, taxes, discounts, and financial balance engine.
     */
    public function pricing(): PricingEngineService
    {
        return $this->getManager()->pricing();
    }

    /**
     * Commercial invoicing and customer payment collection service.
     */
    public function payments(): PaymentService
    {
        return $this->getManager()->payments();
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

    /**
     * Enable or disable automatic migration registration.
     */
    public function autoMigrate(bool $auto = true): self
    {
        if ($this->config !== null) {
            $this->config->set('sales.migrations.auto_migrate', $auto);
        }
        ObelawSalesServiceProvider::autoMigrate($auto);

        return $this;
    }

    /**
     * Disable automatic migration registration.
     */
    public function ignoreMigrations(): self
    {
        return $this->autoMigrate(false);
    }

    /**
     * Set a custom migrations directory path.
     */
    public function setMigrationPath(?string $path): self
    {
        if ($this->config !== null) {
            $this->config->set('sales.migrations.path', $path);
        }
        ObelawSalesServiceProvider::setMigrationPath($path);

        return $this;
    }

    /**
     * Get the active migrations directory path.
     */
    public function getMigrationPath(): string
    {
        return ObelawSalesServiceProvider::getMigrationPath();
    }

    /**
     * Determine whether auto-migration is enabled.
     */
    public function shouldAutoMigrate(): bool
    {
        return ObelawSalesServiceProvider::shouldAutoMigrate();
    }

    /**
     * Get the package default migrations path.
     */
    public function defaultMigrationPath(): string
    {
        return ObelawSalesServiceProvider::defaultMigrationPath();
    }

    /**
     * Execute domain migrations directly.
     *
     * @param string|null $path Custom migrations path override
     * @param array<string, mixed> $options Additional Artisan migrate options
     */
    public function migrate(?string $path = null, array $options = []): int
    {
        $migrationPath = $path ?? $this->getMigrationPath();
        $resolvedPath = realpath($migrationPath) ?: $migrationPath;

        $params = array_merge([
            '--path' => $resolvedPath,
            '--realpath' => true,
        ], $options);

        return Artisan::call('migrate', $params);
    }
}
