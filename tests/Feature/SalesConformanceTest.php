<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Tests\Feature;

use Obelaw\Ium\Facades\Ium;
use Obelaw\Ium\Modules\ModuleScope;
use Obelaw\Ium\Sales\Data\CreateRmaDto;
use Obelaw\Ium\Sales\Data\CreateSalesOrderDto;
use Obelaw\Ium\Sales\Data\MarkShippedDto;
use Obelaw\Ium\Sales\Data\ReceiveRmaGoodsDto;
use Obelaw\Ium\Sales\Data\RmaLineDto;
use Obelaw\Ium\Sales\Data\SalesOrderLineDto;
use Obelaw\Ium\Sales\Enums\OrderStatus;
use Obelaw\Ium\Sales\Enums\RmaDisposition;
use Obelaw\Ium\Sales\Exceptions\InvalidRmaQuantityException;
use Obelaw\Ium\Sales\Models\SalesOrder;
use Obelaw\Ium\Sales\Providers\ObelawSalesServiceProvider;
use Obelaw\Ium\Sales\SalesDomain;
use Obelaw\Ium\Testing\ModuleTestCase;

final class SalesConformanceTest extends ModuleTestCase
{
    protected function moduleServiceProviders(): array
    {
        return [
            ObelawSalesServiceProvider::class,
        ];
    }

    public function test_sales_module_is_registered_and_passes_conformance(): void
    {
        $this->assertModuleRegistered('sales', SalesDomain::class);
        $this->assertSame(ModuleScope::Tenant, Ium::modules()->get('sales')->scope);

        $this->runConformance(function (): void {
            $order = ium()->sales()->orders()->create(new CreateSalesOrderDto(
                currency: 'KWD',
                lines: [
                    new SalesOrderLineDto(sku: 'GOLD-BAR', name: 'Kuwaiti Item', unitPrice: 10.125, quantity: 2.0, taxRate: 10.0),
                ],
            ));

            // 20.250 subtotal + 2.025 tax = 22.275 KWD = 22275 fils (3 decimals)
            $this->assertSame(22275, $order->grand_total_minor);
            $this->assertSame(20250, $order->subtotal_minor);
            $this->assertSame(2025, $order->tax_total_minor);

            $order = ium()->sales()->orders()->confirm($order);
            $order = ium()->sales()->fulfillment()->allocate($order);
            $batch = $order->batches()->firstOrFail();
            $batch = ium()->sales()->fulfillment()->releaseBatch($batch);
            ium()->sales()->fulfillment()->markBatchShipped($batch, new MarkShippedDto(trackingNumber: 'KWT-001'));

            $this->assertSame(OrderStatus::Shipped, $order->fresh()->status);
        });
    }

    public function test_sales_data_and_settings_are_isolated_across_tenants(): void
    {
        $this->assertDataIsolated(
            writeRecord: function (): void {
                ium()->sales()->orders()->create(new CreateSalesOrderDto(
                    customerId: 'CUST-ISO',
                    lines: [
                        new SalesOrderLineDto(sku: 'SKU-ISO', name: 'Isolated Item', unitPrice: 120.0, quantity: 2.0),
                    ],
                ));
            },
            countRecords: fn (): int => SalesOrder::count(),
        );

        $this->assertCacheAndSettingsIsolated();
    }

    public function test_rma_prevents_concurrent_double_returns_and_recalculates_partial_receipt_refund_with_tax(): void
    {
        $this->runInSingleTenantMode(function (): void {
            $order = ium()->sales()->orders()->create(new CreateSalesOrderDto(
                currency: 'USD',
                lines: [
                    new SalesOrderLineDto(sku: 'TAXED-DEV', name: 'Taxed Device', unitPrice: 100.0, quantity: 2.0, taxRate: 15.0),
                ],
            ));

            // Line total = 200 + 30 tax = 230 -> 115 per unit
            $order = ium()->sales()->orders()->confirm($order);
            $order = ium()->sales()->fulfillment()->allocate($order);
            $batch = $order->batches()->firstOrFail();
            $batch = ium()->sales()->fulfillment()->releaseBatch($batch);
            ium()->sales()->fulfillment()->markBatchShipped($batch, new MarkShippedDto());

            $line = $order->lines()->firstOrFail();

            // Open first RMA for 2 units
            $rma = ium()->sales()->rmas()->request(new CreateRmaDto(
                salesOrderId: $order->id,
                reasonCode: 'DEFECTIVE',
                lines: [new RmaLineDto(salesOrderLineId: $line->id, quantityRequested: 2.0)],
            ));

            // Second concurrent RMA against the same line must be rejected
            try {
                ium()->sales()->rmas()->request(new CreateRmaDto(
                    salesOrderId: $order->id,
                    reasonCode: 'DUPLICATE',
                    lines: [new RmaLineDto(salesOrderLineId: $line->id, quantityRequested: 1.0)],
                ));
                $this->fail('Expected InvalidRmaQuantityException for concurrent double return.');
            } catch (InvalidRmaQuantityException) {
                $this->addToAssertionCount(1);
            }

            $rma = ium()->sales()->rmas()->authorize($rma);
            $rmaLine = $rma->lines()->firstOrFail();

            // Receiving more than requested must fail
            try {
                ium()->sales()->rmas()->receiveGoods($rma, new ReceiveRmaGoodsDto(items: [[
                    'rma_line_id' => $rmaLine->id,
                    'quantity_received' => 3.0,
                    'disposition' => RmaDisposition::Restock->value,
                ]]));
                $this->fail('Expected InvalidRmaQuantityException when quantity_received > quantity_requested.');
            } catch (InvalidRmaQuantityException) {
                $this->addToAssertionCount(1);
            }

            // Partial receipt of 1 of 2 units recalculates refund proportionally (115.0 including 15% tax)
            $rma = ium()->sales()->rmas()->receiveGoods($rma, new ReceiveRmaGoodsDto(items: [[
                'rma_line_id' => $rmaLine->id,
                'quantity_received' => 1.0,
                'disposition' => RmaDisposition::Restock->value,
            ]]));

            $this->assertEqualsWithDelta(115.0, (float) $rma->lines()->firstOrFail()->refund_amount, 0.0001);
        });
    }
}
