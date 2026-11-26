<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Mcp;

use Obelaw\Ium\Mcp\Contracts\McpToolset;
use Obelaw\Ium\Sales\Mcp\Tools\CreateDraftSalesOrderTool;
use Obelaw\Ium\Sales\Mcp\Tools\ListSalesOrdersTool;
use Obelaw\Ium\Sales\Mcp\Tools\SearchCustomersTool;

final class SalesToolset implements McpToolset
{
    public function module(): ?string
    {
        return 'sales';
    }

    public function label(): string
    {
        return 'Sales & Orders';
    }

    public function tools(): array
    {
        return [
            SearchCustomersTool::class,
            ListSalesOrdersTool::class,
            CreateDraftSalesOrderTool::class,
        ];
    }

    public function resources(): array
    {
        return [];
    }

    public function prompts(): array
    {
        return [];
    }
}
