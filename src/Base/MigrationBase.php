<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Base;

use Obelaw\Ium\Bases\MigrationBase as StackMigrationBase;

/**
 * Sales module base migration.
 *
 * All sales tables are prefixed with `ium_sales_` via `$this->prefix`.
 */
abstract class MigrationBase extends StackMigrationBase
{
    /**
     * Module name for table prefixing ('sales_').
     */
    protected ?string $module = 'sales_';
}
