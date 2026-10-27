<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Providers;

use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\ServiceProvider;
use Obelaw\Ium\Engine\ObelawConfigManager;
use Obelaw\Ium\Facades\Ium;
use Obelaw\Ium\Sales\SalesDomain;
use Obelaw\Ium\Sales\SalesManager;
use Obelaw\Ium\Sales\Services\AclService;
use Obelaw\Ium\Sales\Services\AuditService;
use Obelaw\Ium\Sales\Services\ChannelService;
use Obelaw\Ium\Sales\Services\FulfillmentService;
use Obelaw\Ium\Sales\Services\OrderService;
use Obelaw\Ium\Sales\Services\PricingEngineService;
use Obelaw\Ium\Sales\Services\QuotationService;
use Obelaw\Ium\Sales\Services\RmaService;

final class ObelawSalesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/sales.php', 'ium.sales');

        $this->app->singleton(SalesManager::class, static fn() => new SalesManager());

        $this->bindServices();

        // Register domain with central Obelawium gateway
        Ium::registerDomain('sales', SalesDomain::class);
    }

    public function boot(): void
    {
        if (ObelawConfigManager::getInstance()->get('obelawium.db.migrations', true)) {
            $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../database/migrations' => database_path('migrations'),
            ], 'ium-sales-migrations');

            $this->publishes([
                __DIR__ . '/../../config/sales.php' => config_path('ium/sales.php'),
            ], 'ium-sales-config');
        }

        AboutCommand::add('Obelawium', static fn() => ['Obelawium Sales' => '0.1.0']);
    }

    private function bindServices(): void
    {
        $services = [
            PricingEngineService::class,
            AuditService::class,
            OrderService::class,
            QuotationService::class,
            FulfillmentService::class,
            RmaService::class,
            ChannelService::class,
            AclService::class,
        ];

        foreach ($services as $service) {
            $this->app->singleton($service);
        }
    }
}
