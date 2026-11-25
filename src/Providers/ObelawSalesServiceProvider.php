<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Providers;

use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Obelaw\Ium\Engine\ObelawConfigManager;
use Obelaw\Ium\Facades\Ium;
use Obelaw\Ium\Modules\Module;
use Obelaw\Ium\Modules\ModuleScope;
use Obelaw\Ium\ObelawiumManager;
use Obelaw\Ium\Sales\Console\Commands\MigrateSalesCommand;
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
use Obelaw\Ium\Sales\Services\CustomerService;
use Obelaw\Ium\Sales\Services\FulfillmentService;
use Obelaw\Ium\Sales\Services\OrderService;
use Obelaw\Ium\Sales\Services\PaymentService;
use Obelaw\Ium\Sales\Services\PricingEngineService;
use Obelaw\Ium\Sales\Services\QuotationService;
use Obelaw\Ium\Sales\Services\RmaService;

final class ObelawSalesServiceProvider extends ServiceProvider
{
    /**
     * Runtime override for automatic migration loading.
     */
    public static ?bool $autoMigrate = null;

    /**
     * Runtime override for custom migration path.
     */
    public static ?string $customMigrationPath = null;

    /**
     * Configure whether migrations should be automatically registered.
     */
    public static function autoMigrate(bool $autoMigrate = true): void
    {
        static::$autoMigrate = $autoMigrate;
    }

    /**
     * Disable automatic migration registration.
     */
    public static function ignoreMigrations(): void
    {
        static::$autoMigrate = false;
    }

    /**
     * Set a custom migrations directory path.
     */
    public static function setMigrationPath(?string $path): void
    {
        static::$customMigrationPath = $path;
    }

    /**
     * Get the default migrations path for the package.
     */
    public static function defaultMigrationPath(): string
    {
        return realpath(__DIR__ . '/../../database/migrations') ?: (__DIR__ . '/../../database/migrations');
    }

    /**
     * Resolve the active migration path.
     */
    public static function getMigrationPath(): string
    {
        if (static::$customMigrationPath !== null && static::$customMigrationPath !== '') {
            return static::$customMigrationPath;
        }

        $configPath = config('ium.sales.migrations.path') ?? config('ium.sales.migration_path');
        if (! empty($configPath) && is_string($configPath)) {
            return $configPath;
        }

        if (class_exists(ObelawConfigManager::class)) {
            $iumPath = ObelawConfigManager::getInstance()->get('sales.migrations.path')
                ?? ObelawConfigManager::getInstance()->get('sales.migration_path');
            if (! empty($iumPath) && is_string($iumPath)) {
                return $iumPath;
            }
        }

        return static::defaultMigrationPath();
    }

    /**
     * Determine if migrations should be loaded automatically.
     */
    public static function shouldAutoMigrate(): bool
    {
        if (static::$autoMigrate !== null) {
            return static::$autoMigrate;
        }

        if (config('ium.migrations.auto_load') === false) {
            return false;
        }

        $configVal = config('ium.sales.migrations.auto_migrate') ?? config('ium.sales.auto_migrate');
        if ($configVal !== null) {
            return (bool) $configVal;
        }

        if (class_exists(ObelawConfigManager::class)) {
            $iumAutoMigrate = ObelawConfigManager::getInstance()->get('sales.migrations.auto_migrate');
            if ($iumAutoMigrate !== null) {
                return (bool) $iumAutoMigrate;
            }

            $globalConfig = ObelawConfigManager::getInstance()->get('obelawium.db.migrations');
            if ($globalConfig !== null) {
                return (bool) $globalConfig;
            }
        }

        return true;
    }

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

        Ium::registerModule(Module::make(
            name: 'sales',
            domain: SalesDomain::class,
            scope: ModuleScope::Tenant,
            migrationPaths: [static::defaultMigrationPath()],
            version: '1.0.0',
            description: 'Sales & Order Management System (OMS) domain module for Obelawium ERP',
        ));
    }

    public function boot(): void
    {
        if (static::shouldAutoMigrate()) {
            $migrationPath = static::getMigrationPath();
            if (is_dir($migrationPath)) {
                $this->loadMigrationsFrom($migrationPath);
            }
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                MigrateSalesCommand::class,
            ]);

            $this->publishes([
                static::defaultMigrationPath() => database_path('migrations'),
            ], 'ium-sales-migrations');

            $this->publishes([
                __DIR__ . '/../../config/sales.php' => config_path('ium/sales.php'),
            ], 'ium-sales-config');
        }

        AboutCommand::add('Obelawium', static fn() => ['Obelawium Sales' => '1.0.0']);

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
            CustomerService::class,
            AclService::class,
        ];

        foreach ($services as $service) {
            $this->app->singleton($service);
        }
    }
}
