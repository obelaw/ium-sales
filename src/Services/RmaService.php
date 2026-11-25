<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Services;

use Obelaw\Ium\Facades\Ium;
use Obelaw\Ium\Sales\Data\CreateRmaDto;
use Obelaw\Ium\Sales\Data\ReceiveRmaGoodsDto;
use Obelaw\Ium\Sales\Enums\RmaDisposition;
use Obelaw\Ium\Sales\Enums\RmaStatus;
use Obelaw\Ium\Sales\Events\ReturnAuthorized;
use Obelaw\Ium\Sales\Events\RmaCreditApproved;
use Obelaw\Ium\Sales\Exceptions\InvalidOrderStateTransitionException;
use Obelaw\Ium\Sales\Exceptions\InvalidRmaQuantityException;
use Obelaw\Ium\Sales\Models\ReturnMerchandiseAuthorization;
use Obelaw\Ium\Sales\Models\RmaLine;
use Obelaw\Ium\Sales\Models\SalesOrder;
use Obelaw\Ium\Sales\Models\SalesOrderLine;

/**
 * Reverse Logistics & Return Merchandise Authorization (RMA) Service.
 */
final class RmaService
{
    private const EPSILON = 0.00001;

    public function __construct(
        private readonly AuditService $audit,
    ) {}

    /**
     * Submit a customer return merchandise request.
     *
     * Accounts for quantities already reserved by open (in-flight) RMAs so concurrent
     * or sequential requests cannot double-return the same order line.
     */
    public function request(CreateRmaDto $dto): ReturnMerchandiseAuthorization
    {
        return Ium::connection()->transaction(function () use ($dto): ReturnMerchandiseAuthorization {
            /** @var SalesOrder $order */
            $order = SalesOrder::where('id', $dto->salesOrderId)->lockForUpdate()->firstOrFail();
            $order->load('lines');
            $linesMap = $order->lines->keyBy('id');

            $openStatuses = [
                RmaStatus::Draft->value,
                RmaStatus::PendingAuthorization->value,
                RmaStatus::Authorized->value,
                RmaStatus::Received->value,
            ];

            /** @var array<int, float> $openRequestedByLine */
            $openRequestedByLine = RmaLine::query()
                ->whereIn('sales_order_line_id', $linesMap->keys()->all())
                ->whereHas('rma', static function ($query) use ($openStatuses): void {
                    $query->whereIn('status', $openStatuses);
                })
                ->selectRaw('sales_order_line_id, SUM(quantity_requested) as total_requested')
                ->groupBy('sales_order_line_id')
                ->pluck('total_requested', 'sales_order_line_id')
                ->map(static fn (mixed $v): float => (float) $v)
                ->all();

            /** @var array<int, float> $cumulativeInRequest */
            $cumulativeInRequest = [];

            // Validate returnable quantities against shipped - returned - open RMAs
            foreach ($dto->lines as $lineDto) {
                /** @var SalesOrderLine|null $orderLine */
                $orderLine = $linesMap->get($lineDto->salesOrderLineId);
                if (! $orderLine) {
                    throw new \InvalidArgumentException("Sales order line [{$lineDto->salesOrderLineId}] not found on order [{$order->order_number}].");
                }

                if ($lineDto->quantityRequested <= 0.0) {
                    throw InvalidRmaQuantityException::forLine($orderLine->sku, $lineDto->quantityRequested, $orderLine->returnableQuantity());
                }

                $openQty = $openRequestedByLine[$orderLine->id] ?? 0.0;
                $alreadyInBatch = $cumulativeInRequest[$orderLine->id] ?? 0.0;
                $maxReturnable = max(0.0, $orderLine->returnableQuantity() - $openQty - $alreadyInBatch);

                if ($lineDto->quantityRequested > $maxReturnable + self::EPSILON) {
                    throw InvalidRmaQuantityException::forLine($orderLine->sku, $lineDto->quantityRequested, $maxReturnable);
                }

                $cumulativeInRequest[$orderLine->id] = $alreadyInBatch + $lineDto->quantityRequested;
            }

            /** @var ReturnMerchandiseAuthorization $rma */
            $rma = ReturnMerchandiseAuthorization::create([
                'rma_number' => $this->generateRmaNumber(),
                'sales_order_id' => $order->id,
                'customer_type' => $order->customer_type,
                'customer_id' => $order->customer_id,
                'status' => RmaStatus::PendingAuthorization,
                'reason_code' => $dto->reasonCode,
                'reason_description' => $dto->reasonDescription,
                'return_type' => $dto->returnType,
                'metadata' => $dto->metadata,
            ]);

            foreach ($dto->lines as $lineDto) {
                /** @var SalesOrderLine $orderLine */
                $orderLine = $linesMap->get($lineDto->salesOrderLineId);
                $estimatedRefund = $this->calculateProportionalRefund($orderLine, $lineDto->quantityRequested);

                RmaLine::create([
                    'rma_id' => $rma->id,
                    'sales_order_line_id' => $lineDto->salesOrderLineId,
                    'quantity_requested' => $lineDto->quantityRequested,
                    'quantity_received' => 0.0,
                    'quantity_approved' => 0.0,
                    'disposition' => RmaDisposition::Pending,
                    'refund_amount' => $estimatedRefund,
                    'notes' => $lineDto->notes,
                ]);
            }

            $this->audit->log(
                auditable: $rma,
                action: 'rma_requested',
                originChannel: $order->channel_code,
                after: ['status' => RmaStatus::PendingAuthorization->value, 'order_number' => $order->order_number],
                reasonCode: $dto->reasonCode
            );

            return $rma->load('lines.orderLine');
        });
    }

    /**
     * Authorize an RMA. Emits ReturnAuthorized.
     */
    public function authorize(ReturnMerchandiseAuthorization $rma): ReturnMerchandiseAuthorization
    {
        if ($rma->status !== RmaStatus::PendingAuthorization && $rma->status !== RmaStatus::Draft) {
            throw InvalidOrderStateTransitionException::transition('RMA', $rma->status->value, RmaStatus::Authorized->value);
        }

        return Ium::connection()->transaction(function () use ($rma): ReturnMerchandiseAuthorization {
            $before = ['status' => $rma->status->value];
            $rma->status = RmaStatus::Authorized;
            $rma->authorized_at = now();
            $rma->save();

            $this->audit->log(
                auditable: $rma,
                action: 'rma_authorized',
                before: $before,
                after: ['status' => RmaStatus::Authorized->value, 'authorized_at' => (string) $rma->authorized_at]
            );

            Ium::connection()->afterCommit(static fn () => event(new ReturnAuthorized($rma)));

            return $rma;
        });
    }

    /**
     * Confirm physical receipt and inspection of returned items.
     *
     * Enforces 0 <= quantity_received <= quantity_requested and recalculates
     * line refund_amount proportionally from actual received quantity (including proportional tax).
     */
    public function receiveGoods(ReturnMerchandiseAuthorization $rma, ReceiveRmaGoodsDto $dto): ReturnMerchandiseAuthorization
    {
        if ($rma->status !== RmaStatus::Authorized) {
            throw InvalidOrderStateTransitionException::transition('RMA', $rma->status->value, RmaStatus::Received->value);
        }

        return Ium::connection()->transaction(function () use ($rma, $dto): ReturnMerchandiseAuthorization {
            /** @var ReturnMerchandiseAuthorization $locked */
            $locked = ReturnMerchandiseAuthorization::where('id', $rma->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== RmaStatus::Authorized) {
                throw InvalidOrderStateTransitionException::transition('RMA', $locked->status->value, RmaStatus::Received->value);
            }

            $locked->load('lines.orderLine');
            $rmaLines = $locked->lines->keyBy('id');

            foreach ($dto->items as $item) {
                $lineId = (int) $item['rma_line_id'];
                /** @var RmaLine|null $rmaLine */
                $rmaLine = $rmaLines->get($lineId);
                if (! $rmaLine) {
                    continue;
                }

                $qtyReceived = (float) $item['quantity_received'];
                $qtyRequested = (float) $rmaLine->quantity_requested;

                if ($qtyReceived < 0.0 || $qtyReceived > $qtyRequested + self::EPSILON) {
                    throw InvalidRmaQuantityException::forLine(
                        $rmaLine->orderLine?->sku ?? ('RMA-LINE-' . $rmaLine->id),
                        $qtyReceived,
                        $qtyRequested
                    );
                }

                $disposition = isset($item['disposition'])
                    ? RmaDisposition::tryFrom((string) $item['disposition']) ?? RmaDisposition::Restock
                    : RmaDisposition::Restock;

                $recalculatedRefund = $rmaLine->orderLine
                    ? $this->calculateProportionalRefund($rmaLine->orderLine, $qtyReceived)
                    : ($qtyRequested > 0.0
                        ? round(((float) $rmaLine->refund_amount / $qtyRequested) * $qtyReceived, 4)
                        : 0.0);

                $rmaLine->update([
                    'quantity_received' => $qtyReceived,
                    'quantity_approved' => $qtyReceived,
                    'disposition' => $disposition,
                    'refund_amount' => $recalculatedRefund,
                    'notes' => $item['notes'] ?? $rmaLine->notes,
                ]);
            }

            $locked->status = RmaStatus::Received;
            $locked->received_at = now();
            $locked->save();

            $this->audit->log(
                auditable: $locked,
                action: 'rma_goods_received',
                after: ['status' => RmaStatus::Received->value]
            );

            return $locked->load('lines.orderLine');
        });
    }

    /**
     * Approve credit note / refund compensation following inspection.
     */
    public function approveCredit(ReturnMerchandiseAuthorization $rma): ReturnMerchandiseAuthorization
    {
        if ($rma->status !== RmaStatus::Received) {
            throw InvalidOrderStateTransitionException::transition('RMA', $rma->status->value, RmaStatus::ApprovedForCredit->value);
        }

        return Ium::connection()->transaction(function () use ($rma): ReturnMerchandiseAuthorization {
            /** @var ReturnMerchandiseAuthorization $locked */
            $locked = ReturnMerchandiseAuthorization::where('id', $rma->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== RmaStatus::Received) {
                throw InvalidOrderStateTransitionException::transition('RMA', $locked->status->value, RmaStatus::ApprovedForCredit->value);
            }

            $locked->load(['lines.orderLine', 'order']);

            foreach ($locked->lines as $line) {
                $orderLine = $line->orderLine;
                if ($orderLine) {
                    $newReturned = (float) $orderLine->returned_quantity + (float) $line->quantity_approved;
                    $orderLine->update([
                        'returned_quantity' => $newReturned,
                    ]);
                }
            }

            $locked->status = RmaStatus::ApprovedForCredit;
            $locked->save();

            $this->audit->log(
                auditable: $locked,
                action: 'rma_approved_for_credit',
                after: ['status' => RmaStatus::ApprovedForCredit->value]
            );

            $totalRefund = round((float) $locked->lines->sum('refund_amount'), 4);
            Ium::connection()->afterCommit(static fn () => event(new RmaCreditApproved($locked, $totalRefund)));

            return $locked;
        });
    }

    /**
     * Reject an RMA return request.
     */
    public function reject(ReturnMerchandiseAuthorization $rma, string $reason): ReturnMerchandiseAuthorization
    {
        $before = ['status' => $rma->status->value];
        $rma->status = RmaStatus::Rejected;
        $rma->save();

        $this->audit->log(
            auditable: $rma,
            action: 'rma_rejected',
            before: $before,
            after: ['status' => RmaStatus::Rejected->value],
            reasonCode: $reason
        );

        return $rma;
    }

    /**
     * Close an authorized and compensated RMA.
     */
    public function close(ReturnMerchandiseAuthorization $rma): ReturnMerchandiseAuthorization
    {
        $before = ['status' => $rma->status->value];
        $rma->status = RmaStatus::Closed;
        $rma->closed_at = now();
        $rma->save();

        $this->audit->log(
            auditable: $rma,
            action: 'rma_closed',
            before: $before,
            after: ['status' => RmaStatus::Closed->value]
        );

        return $rma;
    }

    /**
     * Calculate proportional line refund including discount and tax for a given quantity.
     */
    private function calculateProportionalRefund(SalesOrderLine $orderLine, float $quantity): float
    {
        if ($quantity <= 0.0) {
            return 0.0;
        }

        $lineQty = (float) $orderLine->quantity;
        if ($lineQty > 0.0 && (float) $orderLine->total_amount > 0.0) {
            return round(((float) $orderLine->total_amount / $lineQty) * $quantity, 4);
        }

        $unitNetPrice = $lineQty > 0.0
            ? (float) $orderLine->unit_price - ((float) $orderLine->discount_amount / $lineQty)
            : (float) $orderLine->unit_price;

        return round($unitNetPrice * $quantity, 4);
    }

    private function generateRmaNumber(): string
    {
        return 'RMA-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    }
}
