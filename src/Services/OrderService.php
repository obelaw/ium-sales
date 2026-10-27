<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Services;

use Illuminate\Support\Facades\DB;
use Obelaw\Ium\Sales\Data\CreateSalesOrderDto;
use Obelaw\Ium\Sales\Data\IngestOrderDto;
use Obelaw\Ium\Sales\Enums\OrderStatus;
use Obelaw\Ium\Sales\Enums\SalesConfigEnum;
use Obelaw\Ium\Sales\Events\OrderCancelled;
use Obelaw\Ium\Sales\Events\SalesOrderConfirmed;
use Obelaw\Ium\Sales\Exceptions\CannotCancelReleasedOrderException;
use Obelaw\Ium\Sales\Exceptions\InvalidOrderStateTransitionException;
use Obelaw\Ium\Sales\Models\SalesOrder;
use Obelaw\Ium\Sales\Models\SalesOrderLine;

/**
 * Omnichannel Sales Order Ingestion and Lifecycle Service.
 */
final class OrderService
{
    public function __construct(
        private readonly PricingEngineService $pricing,
        private readonly AuditService $audit,
    ) {}

    /**
     * Create a new draft sales order from a validated DTO.
     */
    public function create(CreateSalesOrderDto $dto): SalesOrder
    {
        return DB::transaction(function () use ($dto): SalesOrder {
            $orderNumber = $dto->orderNumber ?? $this->generateOrderNumber();

            $lineInputs = array_map(fn($line) => [
                'unit_price' => $line->unitPrice,
                'quantity' => $line->quantity,
                'discount_amount' => $line->discountAmount,
                'tax_rate' => $line->taxRate,
            ], $dto->lines);

            $calc = $this->pricing->computeOrderFinancials($lineInputs, $dto->shippingTotal);

            /** @var SalesOrder $order */
            $order = SalesOrder::create([
                'order_number' => $orderNumber,
                'customer_type' => $dto->customerType,
                'customer_id' => $dto->customerId,
                'channel_code' => $dto->channelCode,
                'currency' => $dto->currency,
                'status' => OrderStatus::Draft,
                'payment_status' => $dto->paymentStatus,
                'payment_terms' => $dto->paymentTerms,
                'billing_address' => $dto->billingAddress,
                'shipping_address' => $dto->shippingAddress,
                'subtotal' => $calc['subtotal'],
                'discount_total' => $calc['discount_total'],
                'tax_total' => $calc['tax_total'],
                'shipping_total' => $calc['shipping_total'],
                'grand_total' => $calc['grand_total'],
                'quotation_id' => $dto->quotationId,
                'metadata' => $dto->metadata,
            ]);

            $lineNumber = 1;
            foreach ($dto->lines as $index => $lineDto) {
                $lineCalc = $calc['lines'][$index];

                SalesOrderLine::create([
                    'sales_order_id' => $order->id,
                    'line_number' => $lineNumber++,
                    'orderable_type' => $lineDto->orderableType,
                    'orderable_id' => $lineDto->orderableId,
                    'sku' => $lineDto->sku,
                    'name' => $lineDto->name,
                    'unit_price' => $lineDto->unitPrice,
                    'quantity' => $lineDto->quantity,
                    'allocated_quantity' => 0.0,
                    'shipped_quantity' => 0.0,
                    'returned_quantity' => 0.0,
                    'discount_amount' => $lineCalc['discount'],
                    'tax_rate' => $lineDto->taxRate,
                    'tax_amount' => $lineCalc['tax'],
                    'total_amount' => $lineCalc['total'],
                    'status' => 'pending',
                    'sourcing_node_id' => $lineDto->sourcingNodeId,
                    'metadata' => $lineDto->metadata,
                ]);
            }

            $this->audit->log(
                auditable: $order,
                action: 'created',
                originChannel: $order->channel_code,
                after: ['status' => OrderStatus::Draft->value, 'grand_total' => $order->grand_total]
            );

            return $order->load('lines');
        });
    }

    /**
     * Ingest an omnichannel order from an external channel adapter payload.
     * Enforces canonicalization and idempotent ingestion.
     */
    public function ingest(IngestOrderDto $dto): SalesOrder
    {
        // Check idempotency if channel_order_id is provided
        if ($dto->channelOrderId) {
            $existing = SalesOrder::where('channel_code', $dto->channelCode)
                ->where('metadata->channel_order_id', $dto->channelOrderId)
                ->first();

            if ($existing) {
                return $existing->load('lines');
            }
        }

        $createDto = $dto->toCreateSalesOrderDto();
        $order = $this->create($createDto);

        if (ium_config(SalesConfigEnum::AutoConfirm, false)) {
            $order = $this->confirm($order);
        }

        return $order;
    }

    /**
     * Confirm a sales order.
     *
     * Validates Domain Invariant 1 ("Order Financial Balance Rule") and
     * activates Domain Invariant 2 ("The Price-Lock Law").
     * Emits SalesOrderConfirmed.
     */
    public function confirm(SalesOrder $order): SalesOrder
    {
        $order->loadMissing('lines');

        if ($order->status !== OrderStatus::Draft && $order->status !== OrderStatus::PendingConfirmation) {
            throw InvalidOrderStateTransitionException::transition('SalesOrder', $order->status->value, OrderStatus::Confirmed->value);
        }

        // Domain Invariant 1: Order Financial Balance Rule
        $this->pricing->assertFinancialBalance(
            subtotal: (float) $order->subtotal,
            discountTotal: (float) $order->discount_total,
            taxTotal: (float) $order->tax_total,
            shippingTotal: (float) $order->shipping_total,
            declaredGrandTotal: (float) $order->grand_total,
            reference: $order->order_number
        );

        $before = ['status' => $order->status->value];

        // Transition to Confirmed and record timestamp
        $order->status = OrderStatus::Confirmed;
        $order->confirmed_at = now();
        $order->save();

        $this->audit->log(
            auditable: $order,
            action: 'confirmed',
            originChannel: $order->channel_code,
            before: $before,
            after: [
                'status' => OrderStatus::Confirmed->value,
                'confirmed_at' => (string) $order->confirmed_at,
                'price_locked' => true,
            ]
        );

        event(new SalesOrderConfirmed($order));

        return $order;
    }

    /**
     * Cancel an order according to Domain Invariant 4 ("Split & Cancellation Governance").
     *
     * If already released for picking/packing, cancellation in place is prohibited.
     */
    public function cancel(SalesOrder $order, string $reason): SalesOrder
    {
        $order->loadMissing('lines');

        // Domain Invariant 4: Pre-release vs Post-release cancellation governance
        if (!$order->status->isCancellablePreRelease()) {
            throw CannotCancelReleasedOrderException::forOrder($order->order_number, $order->status->value);
        }

        return DB::transaction(function () use ($order, $reason): SalesOrder {
            $before = ['status' => $order->status->value];

            $order->status = OrderStatus::Cancelled;
            $order->cancelled_at = now();
            $order->cancellation_reason = $reason;
            $order->save();

            // Reset any line allocations
            foreach ($order->lines as $line) {
                $line->update([
                    'allocated_quantity' => 0.0,
                    'status' => 'cancelled',
                ]);
            }

            $this->audit->log(
                auditable: $order,
                action: 'cancelled',
                originChannel: $order->channel_code,
                before: $before,
                after: [
                    'status' => OrderStatus::Cancelled->value,
                    'cancelled_at' => (string) $order->cancelled_at,
                ],
                reasonCode: $reason
            );

            event(new OrderCancelled($order, $reason));

            return $order;
        });
    }

    public function find(int $id): ?SalesOrder
    {
        return SalesOrder::with(['lines', 'batches', 'rmas'])->find($id);
    }

    public function findByNumber(string $orderNumber): ?SalesOrder
    {
        return SalesOrder::with(['lines', 'batches', 'rmas'])->where('order_number', $orderNumber)->first();
    }

    private function generateOrderNumber(): string
    {
        return 'SO-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    }
}
