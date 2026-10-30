<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Integrations\Accounting;

use Illuminate\Support\Facades\Log;
use Obelaw\Ium\Sales\Enums\SalesConfigEnum;
use Obelaw\Ium\Sales\Models\ReturnMerchandiseAuthorization;
use Obelaw\Ium\Sales\Models\SalesOrder;
use Obelaw\Ium\Sales\Models\SalesOrderPayment;
use Throwable;

/**
 * Anti-Corruption Bridge between Sales (OMS) and Accounting (FMS / General Ledger).
 *
 * Translates operational commercial events into zero-sum, balanced double-entry vouchers.
 * Operates strictly with defensive domain existence checks to guarantee zero hard-coupling.
 */
class SalesAccountingBridge
{
    /**
     * Determine if the accounting domain is available and enabled.
     */
    public function isAvailable(): bool
    {
        $enabled = ium_config(SalesConfigEnum::AccountingEnabled, 'auto');

        if ($enabled === false || $enabled === 'disabled' || $enabled === 0 || $enabled === '0') {
            return false;
        }

        if (!function_exists('ium')) {
            return false;
        }

        try {
            return ium()->hasDomain('accounting');
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Ingest commercial invoice into Accounting General Ledger.
     *
     * Debit: Accounts Receivable (1100)
     * Credit: Sales Revenue (4000)
     */
    public function postOrderInvoice(SalesOrder $order): ?object
    {
        if (!$this->isAvailable()) {
            return null;
        }

        $amountMinor = $order->grand_total_minor;
        if ($amountMinor <= 0) {
            return null;
        }

        try {
            $dtoClass = '\Obelaw\Ium\Accounting\Data\AclIngestEventDto';
            if (!class_exists($dtoClass)) {
                return null;
            }

            $receivablesCode = (string) ium_config(SalesConfigEnum::AccountingReceivablesCode, '1100');
            $revenueCode = (string) ium_config(SalesConfigEnum::AccountingRevenueCode, '4000');
            $autoPost = (bool) ium_config(SalesConfigEnum::AccountingAutoPost, true);

            $dto = $dtoClass::from([
                'event_name' => 'sales.order.invoiced',
                'document_reference' => $order->order_number,
                'transaction_date' => $order->confirmed_at?->format('Y-m-d') ?? date('Y-m-d'),
                'amount' => $amountMinor,
                'currency' => $order->currency ?? 'USD',
                'debit_account_code' => $receivablesCode,
                'credit_account_code' => $revenueCode,
                'source_type' => 'sales_order',
                'source_id' => $order->id,
                'auto_post' => $autoPost,
                'narration' => sprintf('Commercial invoice for sales order [%s]', $order->order_number),
                'metadata' => [
                    'order_number' => $order->order_number,
                    'channel_code' => $order->channel_code,
                    'customer_id' => $order->customer_id,
                    'subtotal' => $order->subtotal,
                    'tax_total' => $order->tax_total,
                    'shipping_total' => $order->shipping_total,
                ],
            ]);

            /** @var object $entry */
            $entry = ium()->accounting()->acl()->ingest($dto);

            // Persist the linked accounting voucher ID in order metadata
            $meta = $order->metadata ?? [];
            $meta['accounting_journal_entry_id'] = $entry->id;
            $order->metadata = $meta;
            $order->saveQuietly();

            return $entry;
        } catch (Throwable $e) {
            Log::warning(sprintf('SalesAccountingBridge: failed to post invoice for order [%s]: %s', $order->order_number, $e->getMessage()), [
                'exception' => $e,
            ]);
            return null;
        }
    }

    /**
     * Ingest customer payment receipt into General Ledger.
     *
     * Debit: Bank / Cash (1010)
     * Credit: Accounts Receivable (1100)
     */
    public function postPaymentReceipt(SalesOrder $order, SalesOrderPayment $payment): ?object
    {
        if (!$this->isAvailable()) {
            return null;
        }

        $amountMinor = $payment->amount_minor;
        if ($amountMinor <= 0) {
            return null;
        }

        try {
            $dtoClass = '\Obelaw\Ium\Accounting\Data\AclIngestEventDto';
            if (!class_exists($dtoClass)) {
                return null;
            }

            $bankCode = (string) ium_config(SalesConfigEnum::AccountingBankCode, '1010');
            $receivablesCode = (string) ium_config(SalesConfigEnum::AccountingReceivablesCode, '1100');
            $autoPost = (bool) ium_config(SalesConfigEnum::AccountingAutoPost, true);

            $dto = $dtoClass::from([
                'event_name' => 'sales.payment.received',
                'document_reference' => $payment->payment_reference,
                'transaction_date' => $payment->paid_at?->format('Y-m-d') ?? date('Y-m-d'),
                'amount' => $amountMinor,
                'currency' => $payment->currency ?? $order->currency ?? 'USD',
                'debit_account_code' => $bankCode,
                'credit_account_code' => $receivablesCode,
                'source_type' => 'sales_order_payment',
                'source_id' => $payment->id,
                'auto_post' => $autoPost,
                'narration' => sprintf('Payment receipt [%s] for order [%s]', $payment->payment_reference, $order->order_number),
                'metadata' => [
                    'order_number' => $order->order_number,
                    'payment_reference' => $payment->payment_reference,
                    'payment_method' => $payment->payment_method,
                ],
            ]);

            /** @var object $entry */
            $entry = ium()->accounting()->acl()->ingest($dto);

            $meta = $payment->metadata ?? [];
            $meta['accounting_journal_entry_id'] = $entry->id;
            $payment->metadata = $meta;
            $payment->saveQuietly();

            return $entry;
        } catch (Throwable $e) {
            Log::warning(sprintf('SalesAccountingBridge: failed to post payment [%s]: %s', $payment->payment_reference, $e->getMessage()), [
                'exception' => $e,
            ]);
            return null;
        }
    }

    /**
     * Ingest customer return credit note into General Ledger.
     *
     * Debit: Sales Returns & Allowances (4200)
     * Credit: Accounts Receivable (1100)
     */
    public function postRmaCreditNote(ReturnMerchandiseAuthorization $rma, float $refundAmount): ?object
    {
        if (!$this->isAvailable()) {
            return null;
        }

        $amountMinor = (int) round($refundAmount * 100);
        if ($amountMinor <= 0) {
            return null;
        }

        try {
            $dtoClass = '\Obelaw\Ium\Accounting\Data\AclIngestEventDto';
            if (!class_exists($dtoClass)) {
                return null;
            }

            $returnsCode = (string) ium_config(SalesConfigEnum::AccountingReturnsCode, '4200');
            $receivablesCode = (string) ium_config(SalesConfigEnum::AccountingReceivablesCode, '1100');
            $autoPost = (bool) ium_config(SalesConfigEnum::AccountingAutoPost, true);

            $rma->loadMissing('order');
            $currency = $rma->order?->currency ?? 'USD';

            $dto = $dtoClass::from([
                'event_name' => 'sales.rma.credit_approved',
                'document_reference' => $rma->rma_number,
                'transaction_date' => date('Y-m-d'),
                'amount' => $amountMinor,
                'currency' => $currency,
                'debit_account_code' => $returnsCode,
                'credit_account_code' => $receivablesCode,
                'source_type' => 'rma',
                'source_id' => $rma->id,
                'auto_post' => $autoPost,
                'narration' => sprintf('Credit note for return [%s]', $rma->rma_number),
                'metadata' => [
                    'rma_number' => $rma->rma_number,
                    'order_number' => $rma->order?->order_number,
                    'reason_code' => $rma->reason_code,
                ],
            ]);

            /** @var object $entry */
            $entry = ium()->accounting()->acl()->ingest($dto);

            $meta = $rma->metadata ?? [];
            $meta['accounting_journal_entry_id'] = $entry->id;
            $rma->metadata = $meta;
            $rma->saveQuietly();

            return $entry;
        } catch (Throwable $e) {
            Log::warning(sprintf('SalesAccountingBridge: failed to post credit note for RMA [%s]: %s', $rma->rma_number, $e->getMessage()), [
                'exception' => $e,
            ]);
            return null;
        }
    }

    /**
     * Reverse an invoiced order on the General Ledger via Storno reversal.
     */
    public function reverseOrderInvoice(SalesOrder $order, string $reason): ?object
    {
        if (!$this->isAvailable()) {
            return null;
        }

        $journalEntryId = $order->metadata['accounting_journal_entry_id'] ?? null;
        if (!$journalEntryId) {
            return null;
        }

        try {
            $modelClass = '\Obelaw\Ium\Accounting\Models\JournalEntry';
            if (!class_exists($modelClass)) {
                return null;
            }

            $journalEntry = $modelClass::find((int) $journalEntryId);
            if (!$journalEntry) {
                return null;
            }

            $statusValue = is_object($journalEntry->status) ? $journalEntry->status->value : (string) $journalEntry->status;
            if ($statusValue !== 'posted') {
                return null;
            }

            return ium()->accounting()->journalEntries()->reverse(
                (int) $journalEntryId,
                'sales-bridge',
                sprintf('Order [%s] cancelled: %s', $order->order_number, $reason)
            );
        } catch (Throwable $e) {
            Log::warning(sprintf('SalesAccountingBridge: failed to reverse voucher for order [%s]: %s', $order->order_number, $e->getMessage()), [
                'exception' => $e,
            ]);
            return null;
        }
    }
}
