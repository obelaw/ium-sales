<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Obelaw\Ium\Sales\Data\MarkShippedDto;
use Obelaw\Ium\Sales\Data\SplitOrderDto;
use Obelaw\Ium\Sales\Enums\FulfillmentBatchStatus;
use Obelaw\Ium\Sales\Enums\OrderStatus;
use Obelaw\Ium\Sales\Events\FulfillmentBatchReleased;
use Obelaw\Ium\Sales\Events\OrderSplitted;
use Obelaw\Ium\Sales\Exceptions\InsufficientInventoryException;
use Obelaw\Ium\Sales\Exceptions\InvalidOrderStateTransitionException;
use Obelaw\Ium\Sales\Models\FulfillmentBatch;
use Obelaw\Ium\Sales\Models\FulfillmentBatchLine;
use Obelaw\Ium\Sales\Models\SalesOrder;

/**
 * Multi-Facility Split Fulfillment & Routing Orchestration Service.
 */
final class FulfillmentService
{
    public function __construct(
        private readonly AuditService $audit,
    ) {}

    /**
     * Hard-allocate verified ATP inventory to a confirmed sales order.
     *
     * Enforces Domain Invariant 3 ("Stock Allocation Rule").
     *
     * @param array<string, float>|null $availableAtp Map of sku => available ATP quantity
     */
    public function allocate(SalesOrder $order, string $defaultSourcingNode = 'WH-MAIN', ?array $availableAtp = null): SalesOrder
    {
        $order->loadMissing('lines');

        if ($order->status !== OrderStatus::Confirmed && $order->status !== OrderStatus::Allocated) {
            throw InvalidOrderStateTransitionException::transition('SalesOrder', $order->status->value, OrderStatus::Allocated->value);
        }

        return DB::transaction(function () use ($order, $defaultSourcingNode, $availableAtp): SalesOrder {
            // Pessimistic lock on parent order to prevent race conditions during concurrent allocations
            SalesOrder::where('id', $order->id)->lockForUpdate()->first();

            // Check ATP if available stock array is provided
            if ($availableAtp !== null) {
                foreach ($order->lines as $line) {
                    $requested = $line->unallocatedQuantity();
                    $available = (float) ($availableAtp[$line->sku] ?? 0.0);

                    if ($requested > $available) {
                        throw InsufficientInventoryException::forItem($line->sku, $requested, $available, $defaultSourcingNode);
                    }
                }
            }

            foreach ($order->lines as $line) {
                $line->update([
                    'allocated_quantity' => $line->quantity,
                    'status' => 'allocated',
                    'sourcing_node_id' => $line->sourcing_node_id ?? $defaultSourcingNode,
                ]);
            }

            $order->status = OrderStatus::Allocated;
            $order->save();

            // Create single default batch if no split batches exist yet
            if ($order->batches()->count() === 0) {
                /** @var FulfillmentBatch $batch */
                $batch = FulfillmentBatch::create([
                    'batch_number' => $this->generateBatchNumber(),
                    'sales_order_id' => $order->id,
                    'sourcing_node_id' => $defaultSourcingNode,
                    'status' => FulfillmentBatchStatus::Allocated,
                    'shipping_address' => $order->shipping_address,
                ]);

                foreach ($order->lines as $line) {
                    FulfillmentBatchLine::create([
                        'fulfillment_batch_id' => $batch->id,
                        'sales_order_line_id' => $line->id,
                        'quantity' => $line->quantity,
                    ]);
                }
            }

            $this->audit->log(
                auditable: $order,
                action: 'allocated',
                originChannel: $order->channel_code,
                after: ['status' => OrderStatus::Allocated->value, 'sourcing_node' => $defaultSourcingNode]
            );

            return $order->load(['lines', 'batches.lines']);
        });
    }

    /**
     * Split an order across multiple fulfillment nodes and shipments.
     *
     * @return Collection<int, FulfillmentBatch>
     */
    public function split(SalesOrder $order, SplitOrderDto $dto): Collection
    {
        $order->loadMissing('lines', 'batches');

        return DB::transaction(function () use ($order, $dto): Collection {
            // Clear existing unreleased batches if re-splitting
            foreach ($order->batches as $existingBatch) {
                if ($existingBatch->status === FulfillmentBatchStatus::Allocated || $existingBatch->status === FulfillmentBatchStatus::Pending) {
                    $existingBatch->lines()->delete();
                    $existingBatch->delete();
                }
            }

            $createdBatches = collect();

            foreach ($dto->batches as $batchDto) {
                /** @var FulfillmentBatch $batch */
                $batch = FulfillmentBatch::create([
                    'batch_number' => $this->generateBatchNumber(),
                    'sales_order_id' => $order->id,
                    'sourcing_node_id' => $batchDto->sourcingNodeId,
                    'carrier_code' => $batchDto->carrierCode,
                    'service_level' => $batchDto->serviceLevel,
                    'status' => FulfillmentBatchStatus::Allocated,
                    'shipping_address' => $batchDto->shippingAddress ?? $order->shipping_address,
                    'promised_ship_date' => $batchDto->promisedShipDate,
                    'metadata' => $batchDto->metadata,
                ]);

                foreach ($batchDto->items as $item) {
                    $lineId = (int) $item['order_line_id'];
                    $qty = (float) $item['quantity'];

                    FulfillmentBatchLine::create([
                        'fulfillment_batch_id' => $batch->id,
                        'sales_order_line_id' => $lineId,
                        'quantity' => $qty,
                    ]);
                }

                $createdBatches->push($batch->load('lines'));
            }

            $this->audit->log(
                auditable: $order,
                action: 'split_shipments',
                originChannel: $order->channel_code,
                after: ['batches_count' => $createdBatches->count()]
            );

            event(new OrderSplitted($order, $createdBatches));

            return $createdBatches;
        });
    }

    /**
     * Release a fulfillment batch to WMS for physical pick, pack, and dispatch.
     * Emits FulfillmentBatchReleased.
     */
    public function releaseBatch(FulfillmentBatch $batch): FulfillmentBatch
    {
        $batch->loadMissing('order.lines');

        if ($batch->status !== FulfillmentBatchStatus::Allocated && $batch->status !== FulfillmentBatchStatus::Pending) {
            throw InvalidOrderStateTransitionException::transition('FulfillmentBatch', $batch->status->value, FulfillmentBatchStatus::Released->value);
        }

        return DB::transaction(function () use ($batch): FulfillmentBatch {
            $batch->status = FulfillmentBatchStatus::Released;
            $batch->save();

            // Transition parent order to Released
            $order = $batch->order;
            if ($order && $order->status === OrderStatus::Allocated) {
                $order->status = OrderStatus::Released;
                $order->save();
            }

            $this->audit->log(
                auditable: $batch,
                action: 'released_to_wms',
                after: [
                    'status' => FulfillmentBatchStatus::Released->value,
                    'sourcing_node' => $batch->sourcing_node_id,
                ]
            );

            event(new FulfillmentBatchReleased($batch));

            return $batch;
        });
    }

    /**
     * Record carrier shipment dispatch for a fulfillment batch.
     */
    public function markBatchShipped(FulfillmentBatch $batch, MarkShippedDto $dto): FulfillmentBatch
    {
        $batch->loadMissing(['lines.orderLine', 'order.lines', 'order.batches']);

        return DB::transaction(function () use ($batch, $dto): FulfillmentBatch {
            $batch->status = FulfillmentBatchStatus::Shipped;
            $batch->carrier_code = $dto->carrierCode ?? $batch->carrier_code;
            $batch->tracking_number = $dto->trackingNumber ?? $batch->tracking_number;
            $batch->shipped_at = $dto->shippedAt ? now()->parse($dto->shippedAt) : now();
            $batch->save();

            // Update shipped quantity on lines
            foreach ($batch->lines as $batchLine) {
                $orderLine = $batchLine->orderLine;
                if ($orderLine) {
                    $newShipped = (float) $orderLine->shipped_quantity + (float) $batchLine->quantity;
                    $orderLine->update([
                        'shipped_quantity' => $newShipped,
                        'status' => $newShipped >= (float) $orderLine->quantity ? 'shipped' : 'partially_shipped',
                    ]);
                }
            }

            // Evaluate parent order status
            $order = $batch->order;
            if ($order) {
                $allBatches = $order->batches()->get();
                $allShippedOrDelivered = $allBatches->every(fn(FulfillmentBatch $b) => in_array($b->status, [FulfillmentBatchStatus::Shipped, FulfillmentBatchStatus::Delivered], true));

                $order->status = $allShippedOrDelivered ? OrderStatus::PartiallyShipped : OrderStatus::PartiallyShipped;
                $order->save();
            }

            $this->audit->log(
                auditable: $batch,
                action: 'shipped',
                after: [
                    'status' => FulfillmentBatchStatus::Shipped->value,
                    'tracking_number' => $batch->tracking_number,
                    'carrier' => $batch->carrier_code,
                ]
            );

            return $batch;
        });
    }

    /**
     * Mark a fulfillment batch as delivered to the customer.
     */
    public function markBatchDelivered(FulfillmentBatch $batch): FulfillmentBatch
    {
        $batch->loadMissing(['order.batches']);

        return DB::transaction(function () use ($batch): FulfillmentBatch {
            $batch->status = FulfillmentBatchStatus::Delivered;
            $batch->delivered_at = now();
            $batch->save();

            // If all batches are delivered, order transitions to Completed
            $order = $batch->order;
            if ($order) {
                $allBatches = $order->batches()->get();
                $allDelivered = $allBatches->every(fn(FulfillmentBatch $b) => $b->status === FulfillmentBatchStatus::Delivered);

                if ($allDelivered) {
                    $order->status = OrderStatus::Completed;
                    $order->save();
                }
            }

            $this->audit->log(
                auditable: $batch,
                action: 'delivered',
                after: ['status' => FulfillmentBatchStatus::Delivered->value]
            );

            return $batch;
        });
    }

    private function generateBatchNumber(): string
    {
        return 'FB-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    }
}
