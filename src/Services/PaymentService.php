<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Obelaw\Ium\Sales\Data\RecordOrderPaymentDto;
use Obelaw\Ium\Sales\Events\OrderInvoiced;
use Obelaw\Ium\Sales\Events\OrderPaymentRecorded;
use Obelaw\Ium\Sales\Models\SalesOrder;
use Obelaw\Ium\Sales\Models\SalesOrderPayment;

/**
 * Commercial Invoicing & Payment Collection Lifecycle Service.
 */
final class PaymentService
{
    public function __construct(
        private readonly AuditService $audit,
    ) {}

    /**
     * Record a customer payment transaction against a sales order.
     */
    public function recordPayment(RecordOrderPaymentDto $dto): SalesOrderPayment
    {
        return DB::transaction(function () use ($dto): SalesOrderPayment {
            /** @var SalesOrder $order */
            $order = SalesOrder::where('id', $dto->salesOrderId)->lockForUpdate()->firstOrFail();

            $amountMinor = (int) round($dto->amount * 100);
            $paymentReference = $dto->paymentReference ?? $this->generatePaymentReference($order);
            $currency = $dto->currency ?? $order->currency;
            $paidAt = $dto->paidAt ? \Illuminate\Support\Carbon::parse($dto->paidAt) : now();

            /** @var SalesOrderPayment $payment */
            $payment = SalesOrderPayment::create([
                'sales_order_id' => $order->id,
                'payment_reference' => $paymentReference,
                'payment_method' => $dto->paymentMethod,
                'amount' => $dto->amount,
                'amount_minor' => $amountMinor,
                'currency' => $currency,
                'status' => 'completed',
                'paid_at' => $paidAt,
                'notes' => $dto->notes,
                'metadata' => $dto->metadata,
            ]);

            // Reconcile total payments and update order payment_status
            $totalPaid = (float) $order->payments()->where('status', 'completed')->sum('amount');
            $grandTotal = (float) $order->grand_total;

            $newPaymentStatus = match (true) {
                $totalPaid >= $grandTotal - 0.0001 && $totalPaid <= $grandTotal + 0.0001 => 'paid',
                $totalPaid > $grandTotal + 0.0001 => 'overpaid',
                $totalPaid > 0.0 => 'partially_paid',
                default => 'pending',
            };

            $beforeStatus = $order->payment_status;
            $order->payment_status = $newPaymentStatus;
            $order->saveQuietly();

            $this->audit->log(
                auditable: $order,
                action: 'payment_recorded',
                originChannel: $order->channel_code,
                before: ['payment_status' => $beforeStatus],
                after: [
                    'payment_status' => $newPaymentStatus,
                    'payment_reference' => $paymentReference,
                    'amount_paid' => $dto->amount,
                    'total_paid' => $totalPaid,
                ]
            );

            event(new OrderPaymentRecorded($order, $payment));

            return $payment;
        });
    }

    /**
     * Issue a formal commercial invoice for a sales order.
     */
    public function issueInvoice(SalesOrder $order): string
    {
        return DB::transaction(function () use ($order): string {
            $order = SalesOrder::where('id', $order->id)->lockForUpdate()->firstOrFail();

            $metadata = $order->metadata ?? [];
            if (!empty($metadata['invoice_number'])) {
                return (string) $metadata['invoice_number'];
            }

            $invoiceNumber = 'INV-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $metadata['invoice_number'] = $invoiceNumber;
            $metadata['invoiced_at'] = now()->toIso8601String();

            $order->metadata = $metadata;
            $order->saveQuietly();

            $this->audit->log(
                auditable: $order,
                action: 'invoice_issued',
                originChannel: $order->channel_code,
                after: ['invoice_number' => $invoiceNumber]
            );

            event(new OrderInvoiced($order, $invoiceNumber));

            return $invoiceNumber;
        });
    }

    /**
     * Retrieve all payment records associated with a sales order.
     *
     * @return Collection<int, SalesOrderPayment>
     */
    public function getPaymentsForOrder(int $orderId): Collection
    {
        return SalesOrderPayment::where('sales_order_id', $orderId)
            ->orderBy('paid_at', 'desc')
            ->get();
    }

    private function generatePaymentReference(SalesOrder $order): string
    {
        return 'PAY-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    }
}
