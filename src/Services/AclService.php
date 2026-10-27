<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Services;

use Obelaw\Ium\Sales\Data\IngestOrderDto;
use Obelaw\Ium\Sales\Models\FulfillmentBatch;
use Obelaw\Ium\Sales\Models\SalesOrder;

/**
 * Anti-Corruption Layer (ACL) for Inbound Ingestion and Outbound Domain Translation.
 */
final class AclService
{
    public function __construct(
        private readonly OrderService $orders,
    ) {}

    /**
     * Ingest an external cart / POS / EDI payload through the ACL.
     *
     * @param array<string, mixed> $payload
     */
    public function ingestFromChannel(array $payload): SalesOrder
    {
        $dto = IngestOrderDto::from($payload);
        return $this->orders->ingest($dto);
    }

    /**
     * Translate a released batch into an external WMS pick/pack instruction.
     *
     * @return array<string, mixed>
     */
    public function exportToWmsPayload(FulfillmentBatch $batch): array
    {
        $batch->loadMissing(['order.lines', 'lines.orderLine']);

        return [
            'instruction_type' => 'PICK_PACK_SHIP',
            'batch_number' => $batch->batch_number,
            'order_reference' => $batch->order->order_number,
            'sourcing_node_id' => $batch->sourcing_node_id,
            'carrier_code' => $batch->carrier_code,
            'service_level' => $batch->service_level,
            'shipping_address' => $batch->shipping_address,
            'items' => $batch->lines->map(fn($line) => [
                'sku' => $line->orderLine?->sku,
                'name' => $line->orderLine?->name,
                'quantity' => (float) $line->quantity,
            ])->toArray(),
        ];
    }

    /**
     * Translate a batch into a TMS waybill dispatch request.
     *
     * @return array<string, mixed>
     */
    public function exportToTmsPayload(FulfillmentBatch $batch): array
    {
        $batch->loadMissing('order');

        return [
            'order_reference' => $batch->order->order_number,
            'batch_number' => $batch->batch_number,
            'destination_address' => $batch->shipping_address,
            'service_level' => $batch->service_level,
            'carrier_code' => $batch->carrier_code,
        ];
    }

    /**
     * Translate a confirmed order into an Accounting / FMS billing instruction.
     *
     * @return array<string, mixed>
     */
    public function exportToFmsPayload(SalesOrder $order): array
    {
        $order->loadMissing('lines');

        return [
            'document_type' => 'CUSTOMER_INVOICE',
            'order_reference' => $order->order_number,
            'customer_alias' => $order->customer_id,
            'customer_type' => $order->customer_type,
            'currency' => $order->currency,
            'subtotal' => (float) $order->subtotal,
            'discount_total' => (float) $order->discount_total,
            'tax_total' => (float) $order->tax_total,
            'shipping_total' => (float) $order->shipping_total,
            'grand_total' => (float) $order->grand_total,
            'lines' => $order->lines->map(fn($l) => [
                'sku' => $l->sku,
                'description' => $l->name,
                'quantity' => (float) $l->quantity,
                'unit_price' => (float) $l->unit_price,
                'discount' => (float) $l->discount_amount,
                'tax' => (float) $l->tax_amount,
                'total' => (float) $l->total_amount,
            ])->toArray(),
        ];
    }
}
