<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Obelaw\Ium\Mcp\Base\IumTool;
use Obelaw\Ium\Sales\Models\SalesOrder;

#[Name('sales.list-orders')]
#[Title('List Sales Orders')]
#[Description('List recent sales orders with optional status or customer_id filters.')]
#[IsReadOnly]
#[IsIdempotent]
final class ListSalesOrdersTool extends IumTool
{
    public function module(): ?string
    {
        return 'sales';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->description('Optional order status filter (e.g. draft, confirmed, cancelled).'),
            'customer_id' => $schema->integer()->description('Optional customer ID filter.'),
            'limit' => $schema->integer()->description('Maximum orders to return (1-50, default 15).')->default(15),
        ];
    }

    protected function execute(Request $request): array
    {
        $limit = max(1, min(50, (int) ($request->get('limit') ?? 15)));
        $builder = SalesOrder::query()->with('lines')->orderByDesc('id');

        if ($request->get('customer_id') !== null) {
            $builder->where('customer_id', $request->get('customer_id'));
        }

        $status = trim((string) ($request->get('status') ?? ''));
        if ($status !== '') {
            $builder->where('status', $status);
        }

        $orders = $builder->limit($limit)->get()->map(static fn (SalesOrder $order): array => [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status instanceof \BackedEnum ? $order->status->value : (string) $order->status,
            'currency' => $order->currency,
            'subtotal' => (float) $order->subtotal,
            'tax_total' => (float) $order->tax_total,
            'grand_total' => (float) $order->grand_total,
            'lines_count' => $order->lines->count(),
            'created_at' => $order->created_at?->toIso8601String(),
        ])->all();

        return [
            'count' => count($orders),
            'orders' => $orders,
        ];
    }
}
