<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Obelaw\Ium\Mcp\Base\IumTool;
use Obelaw\Ium\Sales\Data\CreateSalesOrderDto;
use Obelaw\Ium\Sales\Models\Customer;

#[Name('sales.create-draft-order')]
#[Title('Create Draft Sales Order')]
#[Description('Creates a Draft Sales Order via ium()->sales()->orders()->create(). Enforces Maker-Checker governance: orders remain in draft status for human confirmation.')]
#[IsDestructive(false)]
final class CreateDraftSalesOrderTool extends IumTool
{
    public function module(): ?string
    {
        return 'sales';
    }

    public function isWriteAction(): bool
    {
        return true;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'customer_id' => $schema->integer()->description('Optional customer ID (resolve via sales.search-customers).'),
            'currency' => $schema->string()->description('3-letter ISO currency code (default USD).')->default('USD'),
            'channel_code' => $schema->string()->description('Sales channel code (default mcp).')->default('mcp'),
            'shipping_total' => $schema->number()->description('Shipping amount (default 0).')->default(0.0),
            'lines' => $schema->array()->items(
                $schema->object([
                    'sku' => $schema->string()->description('Product SKU.')->required(),
                    'name' => $schema->string()->description('Line item name.')->required(),
                    'unit_price' => $schema->number()->description('Unit price (>= 0).')->required(),
                    'quantity' => $schema->number()->description('Quantity (> 0, default 1).')->default(1.0),
                    'discount_amount' => $schema->number()->description('Discount amount (default 0).')->default(0.0),
                    'tax_rate' => $schema->number()->description('Tax rate percentage (default 0).')->default(0.0),
                ])
            )->description('One or more sales order line items.')->required(),
        ];
    }

    protected function execute(Request $request): array
    {
        $validated = $request->validate([
            'customer_id' => ['nullable', 'integer'],
            'currency' => ['nullable', 'string', 'size:3'],
            'channel_code' => ['nullable', 'string', 'max:64'],
            'shipping_total' => ['nullable', 'numeric', 'min:0'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.sku' => ['required', 'string', 'max:100'],
            'lines.*.name' => ['required', 'string', 'max:255'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.quantity' => ['nullable', 'numeric', 'gt:0'],
            'lines.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'lines.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $order = ium()->sales()->orders()->create(CreateSalesOrderDto::from([
            'customer_type' => isset($validated['customer_id']) ? Customer::class : null,
            'customer_id' => $validated['customer_id'] ?? null,
            'channel_code' => (string) ($validated['channel_code'] ?? 'mcp'),
            'currency' => strtoupper((string) ($validated['currency'] ?? 'USD')),
            'shipping_total' => (float) ($validated['shipping_total'] ?? 0.0),
            'lines' => $validated['lines'],
        ]));

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status instanceof \BackedEnum ? $order->status->value : (string) $order->status,
            'currency' => $order->currency,
            'subtotal' => (float) $order->subtotal,
            'tax_total' => (float) $order->tax_total,
            'grand_total' => (float) $order->grand_total,
            'lines_count' => $order->lines->count(),
            'message' => "Draft Sales Order [{$order->order_number}] created and awaiting confirmation.",
            '_resource_type' => 'sales_order',
            '_resource_id' => $order->id,
        ];
    }
}
