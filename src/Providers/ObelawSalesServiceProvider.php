<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Providers;

use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Obelaw\Ium\Engine\ObelawConfigManager;
use Obelaw\Ium\Facades\Ium;
use Obelaw\Ium\ObelawiumManager;
use Obelaw\Ium\Sales\Events\OrderCancelled;
use Obelaw\Ium\Sales\Events\OrderPaymentRecorded;
use Obelaw\Ium\Sales\Events\RmaCreditApproved;
use Obelaw\Ium\Sales\Events\SalesOrderConfirmed;
use Obelaw\Ium\Sales\Integrations\Accounting\Listeners\PostOrderInvoiceToAccountingListener;
use Obelaw\Ium\Sales\Integrations\Accounting\Listeners\PostPaymentToAccountingListener;
use Obelaw\Ium\Sales\Integrations\Accounting\Listeners\PostRmaCreditToAccountingListener;
use Obelaw\Ium\Sales\Integrations\Accounting\Listeners\ReverseOrderOnAccountingListener;
use Obelaw\Ium\Sales\Integrations\Accounting\SalesAccountingBridge;
use Obelaw\Ium\Sales\SalesDomain;
use Obelaw\Ium\Sales\SalesManager;
use Obelaw\Ium\Sales\Services\AclService;
use Obelaw\Ium\Sales\Services\AuditService;
use Obelaw\Ium\Sales\Services\ChannelService;
use Obelaw\Ium\Sales\Services\FulfillmentService;
use Obelaw\Ium\Sales\Services\OrderService;
use Obelaw\Ium\Sales\Services\PaymentService;
use Obelaw\Ium\Sales\Services\PricingEngineService;
use Obelaw\Ium\Sales\Services\QuotationService;
use Obelaw\Ium\Sales\Services\RmaService;

final class ObelawSalesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/sales.php', 'ium.sales');

        if (! $this->app->bound(ObelawiumManager::class)) {
            $this->app->singleton(ObelawiumManager::class, static function () {
                return ObelawiumManager::getInstance();
            });
        }

        $this->app->singleton(SalesManager::class, static fn() => new SalesManager());
        $this->app->singleton(SalesAccountingBridge::class);

        $this->bindServices();

        // Register domain with central Obelawium gateway
        ObelawiumManager::getInstance()->registerDomain('sales', SalesDomain::class);
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

        $this->registerEventSubscribers();
    }

    private function registerEventSubscribers(): void
    {
        Event::listen(SalesOrderConfirmed::class, PostOrderInvoiceToAccountingListener::class);
        Event::listen(OrderPaymentRecorded::class, PostPaymentToAccountingListener::class);
        Event::listen(OrderCancelled::class, ReverseOrderOnAccountingListener::class);
        Event::listen(RmaCreditApproved::class, PostRmaCreditToAccountingListener::class);
    }

    private function bindServices(): void
    {
        $services = [
            PricingEngineService::class,
            AuditService::class,
            OrderService::class,
            PaymentService::class,
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
