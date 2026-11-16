<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Tests\Feature;

use Obelaw\Ium\Engine\ObelawConfigManager;
use Obelaw\Ium\Sales\Providers\ObelawSalesServiceProvider;

afterEach(function () {
    config([
        'ium.sales.migrations.auto_migrate' => true,
        'ium.sales.migrations.path' => null,
        'ium.sales.auto_migrate' => null,
        'ium.sales.migration_path' => null,
    ]);
    ObelawSalesServiceProvider::$autoMigrate = null;
    ObelawSalesServiceProvider::$customMigrationPath = null;
    if (class_exists(ObelawConfigManager::class)) {
        ObelawConfigManager::getInstance()->reset();
    }
});

it('defaults auto_migrate to true and migration path to package default migration path', function () {
    expect(ObelawSalesServiceProvider::shouldAutoMigrate())->toBeTrue();
    expect(ObelawSalesServiceProvider::getMigrationPath())->toBe(ObelawSalesServiceProvider::defaultMigrationPath());
    expect(ium()->sales()->shouldAutoMigrate())->toBeTrue();
    expect(ium()->sales()->getMigrationPath())->toBe(ObelawSalesServiceProvider::defaultMigrationPath());
});

it('disables auto migration via ium.sales.migrations.auto_migrate configuration', function () {
    config(['ium.sales.migrations.auto_migrate' => false]);

    expect(ObelawSalesServiceProvider::shouldAutoMigrate())->toBeFalse();
    expect(ium()->sales()->shouldAutoMigrate())->toBeFalse();
});

it('disables auto migration via flat ium.sales.auto_migrate configuration', function () {
    config([
        'ium.sales.migrations.auto_migrate' => null,
        'ium.sales.auto_migrate' => false,
    ]);

    expect(ObelawSalesServiceProvider::shouldAutoMigrate())->toBeFalse();
});

it('disables auto migration via ObelawConfigManager global switch', function () {
    config([
        'ium.sales.migrations.auto_migrate' => null,
        'ium.sales.auto_migrate' => null,
    ]);

    ObelawConfigManager::getInstance()->set('obelawium.db.migrations', false);
    expect(ObelawSalesServiceProvider::shouldAutoMigrate())->toBeFalse();

    ObelawConfigManager::getInstance()->set('obelawium.db.migrations', true);
    expect(ObelawSalesServiceProvider::shouldAutoMigrate())->toBeTrue();

    ObelawConfigManager::getInstance()->reset();
});

it('allows programmatic override of auto migration via ServiceProvider and Domain gateway', function () {
    ObelawSalesServiceProvider::autoMigrate(false);
    expect(ObelawSalesServiceProvider::shouldAutoMigrate())->toBeFalse();

    ObelawSalesServiceProvider::ignoreMigrations();
    expect(ObelawSalesServiceProvider::shouldAutoMigrate())->toBeFalse();

    ium()->sales()->autoMigrate(true);
    expect(ObelawSalesServiceProvider::shouldAutoMigrate())->toBeTrue();

    ium()->sales()->ignoreMigrations();
    expect(ObelawSalesServiceProvider::shouldAutoMigrate())->toBeFalse();
});

it('allows custom migration path via config', function () {
    $customPath = sys_get_temp_dir() . '/custom_sales_migrations';

    config(['ium.sales.migrations.path' => $customPath]);
    expect(ObelawSalesServiceProvider::getMigrationPath())->toBe($customPath);
    expect(ium()->sales()->getMigrationPath())->toBe($customPath);
});

it('allows custom migration path via Domain gateway setMigrationPath', function () {
    $customPath = sys_get_temp_dir() . '/custom_sales_migrations_via_domain';

    ium()->sales()->setMigrationPath($customPath);
    expect(ObelawSalesServiceProvider::getMigrationPath())->toBe($customPath);
    expect(ium()->sales()->getMigrationPath())->toBe($customPath);
});
