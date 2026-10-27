<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Tests;

use Obelaw\Ium\Providers\ObelawiumServiceProvider;
use Obelaw\Ium\Sales\Providers\ObelawSalesServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            ObelawiumServiceProvider::class,
            ObelawSalesServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
        $app['config']->set('app.key', 'base64:6Cu/eaCLkLzW19Y7W1+kZ+RzF1/5tN2qE8O7r5m4r9s=');
        $app['config']->set('ium.sales.price_lock_strict', true);
        $app['config']->set('ium.sales.audit_enabled', true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate')->run();
    }
}
