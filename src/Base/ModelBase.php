<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Base;

use Obelaw\Ium\Bases\ModelBase as StackBaseModel;

/**
 * Sales module base model.
 *
 * Applies the `ium_sales_` table prefix.
 */
class ModelBase extends StackBaseModel
{
    /**
     * Module name for table prefixing ('ium_sales_').
     *
     * @var string|null $module
     */
    protected ?string $module = 'sales_';
}
