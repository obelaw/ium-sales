<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Services;

use Illuminate\Support\Facades\DB;
use Obelaw\Ium\Sales\Data\CreateRmaDto;
use Obelaw\Ium\Sales\Data\ReceiveRmaGoodsDto;
use Obelaw\Ium\Sales\Enums\RmaDisposition;
use Obelaw\Ium\Sales\Enums\RmaStatus;
use Obelaw\Ium\Sales\Events\ReturnAuthorized;
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
    public function __construct(
        private readonly AuditService $audit,
    ) {}

    /**
     * Submit a customer return merchandise request.
     */
    public function request(CreateRmaDto $dto): ReturnMerchandiseAuthorization
    {
        /** @var SalesOrder $order */
        $order = SalesOrder::with('lines')->findOrFail($dto->salesOrderId);

        return DB::transaction(function () use ($order, $dto): ReturnMerchandiseAuthorization {
            $linesMap = $order->lines->keyBy('id');

            // Validate returnable quantities
            foreach ($dto->lines as $lineDto) {
                /** @var SalesOrderLine|null $orderLine */
                $orderLine = $linesMap->get($lineDto->salesOrderLineId);
                if (!$orderLine) {
                    throw new \InvalidArgumentException("Sales order line [{$lineDto->salesOrderLineId}] not found on order [{$order->order_number}].");
                }

                $maxReturnable = $orderLine->returnableQuantity();
                if ($lineDto->quantityRequested > $maxReturnable) {
                    throw InvalidRmaQuantityException::forLine($orderLine->sku, $lineDto->quantityRequested, $maxReturnable);
                }
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
                $unitNetPrice = (float) $orderLine->unit_price - ((float) $orderLine->discount_amount / (float) $orderLine->quantity);
                $estimatedRefund = round($unitNetPrice * $lineDto->quantityRequested, 4);

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

        event(new ReturnAuthorized($rma));

        return $rma;
    }

    /**
     * Confirm physical receipt and inspection of returned items.
     */
    public function receiveGoods(ReturnMerchandiseAuthorization $rma, ReceiveRmaGoodsDto $dto): ReturnMerchandiseAuthorization
    {
        if ($rma->status !== RmaStatus::Authorized) {
            throw InvalidOrderStateTransitionException::transition('RMA', $rma->status->value, RmaStatus::Received->value);
        }

        return DB::transaction(function () use ($rma, $dto): ReturnMerchandiseAuthorization {
            $rma->loadMissing('lines');
            $rmaLines = $rma->lines->keyBy('id');

            foreach ($dto->items as $item) {
                $lineId = (int) $item['rma_line_id'];
                /** @var RmaLine|null $rmaLine */
                $rmaLine = $rmaLines->get($lineId);
                if ($rmaLine) {
                    $disposition = isset($item['disposition'])
                        ? RmaDisposition::tryFrom((string) $item['disposition']) ?? RmaDisposition::Restock
                        : RmaDisposition::Restock;

                    $rmaLine->update([
                        'quantity_received' => (float) $item['quantity_received'],
                        'quantity_approved' => (float) $item['quantity_received'],
                        'disposition' => $disposition,
                        'notes' => $item['notes'] ?? $rmaLine->notes,
                    ]);
                }
            }

            $rma->status = RmaStatus::Received;
            $rma->received_at = now();
            $rma->save();

            $this->audit->log(
                auditable: $rma,
                action: 'rma_goods_received',
                after: ['status' => RmaStatus::Received->value]
            );

            return $rma->load('lines');
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

        return DB::transaction(function () use ($rma): ReturnMerchandiseAuthorization {
            $rma->loadMissing(['lines.orderLine', 'order']);

            foreach ($rma->lines as $line) {
                $orderLine = $line->orderLine;
                if ($orderLine) {
                    $newReturned = (float) $orderLine->returned_quantity + (float) $line->quantity_approved;
                    $orderLine->update([
                        'returned_quantity' => $newReturned,
                    ]);
                }
            }

            $rma->status = RmaStatus::ApprovedForCredit;
            $rma->save();

            $this->audit->log(
                auditable: $rma,
                action: 'rma_approved_for_credit',
                after: ['status' => RmaStatus::ApprovedForCredit->value]
            );

            return $rma;
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

    private function generateRmaNumber(): string
    {
        return 'RMA-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    }
}
