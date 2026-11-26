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
use Obelaw\Ium\Sales\Models\Customer;

#[Name('sales.search-customers')]
#[Title('Search Sales Customers')]
#[Description('Search customers by name, email, phone, or company. Always call this before creating a draft sales order to verify real customer IDs.')]
#[IsReadOnly]
#[IsIdempotent]
final class SearchCustomersTool extends IumTool
{
    public function module(): ?string
    {
        return 'sales';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Optional search text matched against customer name, email, phone, or company.'),
            'limit' => $schema->integer()->description('Maximum customers to return (1-50, default 15).')->default(15),
        ];
    }

    protected function execute(Request $request): array
    {
        $queryText = trim((string) ($request->get('query') ?? ''));
        $limit = max(1, min(50, (int) ($request->get('limit') ?? 15)));

        $builder = Customer::query()->where('is_active', true)->orderBy('name');

        if ($queryText !== '') {
            $builder->where(static function ($q) use ($queryText): void {
                $q->where('name', 'like', "%{$queryText}%")
                    ->orWhere('email', 'like', "%{$queryText}%")
                    ->orWhere('phone', 'like', "%{$queryText}%")
                    ->orWhere('company', 'like', "%{$queryText}%");
            });
        }

        $customers = $builder->limit($limit)->get()->map(static fn (Customer $customer): array => [
            'id' => $customer->id,
            'name' => $customer->name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'company' => $customer->company,
            'currency' => $customer->currency,
        ])->all();

        return [
            'count' => count($customers),
            'customers' => $customers,
        ];
    }
}
