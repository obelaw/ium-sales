<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Integrations\Accounting;

use Illuminate\Support\Facades\Log;
use Obelaw\Ium\Sales\Enums\SalesConfigEnum;
use Obelaw\Ium\Sales\Models\ReturnMerchandiseAuthorization;
use Obelaw\Ium\Sales\Models\SalesOrder;
use Obelaw\Ium\Sales\Models\SalesOrderPayment;
use Obelaw\Ium\Support\Money;
use Throwable;

/**
 * Anti-Corruption Bridge between Sales (OMS) and Accounting (FMS / General Ledger).
 *
 * Translates operational commercial events into zero-sum, balanced double-entry vouchers
 * compatible with `obelaw/ium-accounting` v1.0 (`AclIngestData`).
 * Operates strictly with defensive domain existence checks to guarantee zero hard-coupling.
 */
class SalesAccountingBridge
{
    private const ACL_DTO_CLASS = '\Obelaw\Ium\Accounting\Data\AclIngestData';

    /**
     * Determine if the accounting domain is available and enabled.
     */
    public function isAvailable(): bool
    {
        $enabled = ium_config(SalesConfigEnum::AccountingEnabled, 'auto');

        if ($enabled === false || $enabled === 'disabled' || $enabled === 0 || $enabled === '0') {
            return false;
        }

        if (! function_exists('ium')) {
            return false;
        }

        try {
            return ium()->hasDomain('accounting') && class_exists(self::ACL_DTO_CLASS);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Ingest commercial invoice into Accounting General Ledger.
     *
     * Multi-line zero-sum voucher when tax or shipping is present:
     *   Debit:  Accounts Receivable (1100)  = grand_total
     *   Credit: Sales Revenue (4000)        = grand_total - tax - shipping
     *   Credit: Tax Payable (2200)          = tax_total       (when > 0)
     *   Credit: Shipping Revenue (4100)     = shipping_total  (when > 0)
     */
    public function postOrderInvoice(SalesOrder $order): ?object
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $currency = $order->currency ?: 'USD';
        $grandTotalMinor = $order->grand_total_minor;
        if ($grandTotalMinor <= 0) {
            return null;
        }

        try {
            $dtoClass = self::ACL_DTO_CLASS;
            $receivablesCode = (string) ium_config(SalesConfigEnum::AccountingReceivablesCode, '1100');
            $revenueCode = (string) ium_config(SalesConfigEnum::AccountingRevenueCode, '4000');
            $taxPayableCode = (string) ium_config(SalesConfigEnum::AccountingTaxPayableCode, '2200');
            $shippingCode = (string) ium_config(SalesConfigEnum::AccountingShippingCode, '4100');
            $autoPost = (bool) ium_config(SalesConfigEnum::AccountingAutoPost, true);

            $taxMinor = $order->tax_total_minor;
            $shippingMinor = $order->shipping_total_minor;
            $netRevenueMinor = $grandTotalMinor - $taxMinor - $shippingMinor;

            $payload = [
                'event_name' => 'sales.order.invoiced',
                'document_reference' => $order->order_number,
                'transaction_date' => $order->confirmed_at?->format('Y-m-d') ?? date('Y-m-d'),
                'currency' => $currency,
                'source_type' => 'sales_order',
                'source_id' => $order->id,
                'auto_post' => $autoPost,
                'idempotency_key' => 'sales:order:invoice:' . $order->id,
                'narration' => sprintf('Commercial invoice for sales order [%s]', $order->order_number),
                'metadata' => [
                    'order_number' => $order->order_number,
                    'channel_code' => $order->channel_code,
                    'customer_id' => $order->customer_id,
                    'subtotal' => $order->subtotal,
                    'discount_total' => $order->discount_total,
                    'tax_total' => $order->tax_total,
                    'shipping_total' => $order->shipping_total,
                ],
            ];

            if ($taxMinor > 0 || $shippingMinor > 0) {
                $lines = [
                    [
                        'account_code' => $receivablesCode,
                        'type' => 'debit',
                        'amount' => $grandTotalMinor,
                        'narration' => sprintf('Accounts receivable for order [%s]', $order->order_number),
                    ],
                ];

                if ($netRevenueMinor > 0) {
                    $lines[] = [
                        'account_code' => $revenueCode,
                        'type' => 'credit',
                        'amount' => $netRevenueMinor,
                        'narration' => sprintf('Sales revenue for order [%s]', $order->order_number),
                    ];
                }

                if ($taxMinor > 0) {
                    $lines[] = [
                        'account_code' => $taxPayableCode,
                        'type' => 'credit',
                        'amount' => $taxMinor,
                        'narration' => sprintf('Sales tax payable for order [%s]', $order->order_number),
                    ];
                }

                if ($shippingMinor > 0) {
                    $lines[] = [
                        'account_code' => $shippingCode,
                        'type' => 'credit',
                        'amount' => $shippingMinor,
                        'narration' => sprintf('Shipping revenue for order [%s]', $order->order_number),
                    ];
                }

                $payload['lines'] = $lines;
            } else {
                $payload['amount'] = $grandTotalMinor;
                $payload['debit_account_code'] = $receivablesCode;
                $payload['credit_account_code'] = $revenueCode;
            }

            $dto = $dtoClass::fromArray($payload);

            /** @var object $entry */
            $entry = ium()->accounting()->acl()->ingest($dto);

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
        if (! $this->isAvailable()) {
            return null;
        }

        $currency = $payment->currency ?: ($order->currency ?: 'USD');
        $amountMinor = $payment->amount_minor > 0
            ? (int) $payment->amount_minor
            : Money::fromMajor((float) $payment->amount, $currency)->amount;

        if ($amountMinor <= 0) {
            return null;
        }

        try {
            $dtoClass = self::ACL_DTO_CLASS;
            $bankCode = (string) ium_config(SalesConfigEnum::AccountingBankCode, '1010');
            $receivablesCode = (string) ium_config(SalesConfigEnum::AccountingReceivablesCode, '1100');
            $autoPost = (bool) ium_config(SalesConfigEnum::AccountingAutoPost, true);

            $dto = $dtoClass::fromArray([
                'event_name' => 'sales.payment.received',
                'document_reference' => $payment->payment_reference,
                'transaction_date' => $payment->paid_at?->format('Y-m-d') ?? date('Y-m-d'),
                'amount' => $amountMinor,
                'currency' => $currency,
                'debit_account_code' => $bankCode,
                'credit_account_code' => $receivablesCode,
                'source_type' => 'sales_order_payment',
                'source_id' => $payment->id,
                'auto_post' => $autoPost,
                'idempotency_key' => 'sales:payment:' . $payment->id,
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
        if (! $this->isAvailable()) {
            return null;
        }

        $rma->loadMissing('order');
        $currency = $rma->order?->currency ?: 'USD';
        $amountMinor = Money::fromMajor($refundAmount, $currency)->amount;

        if ($amountMinor <= 0) {
            return null;
        }

        try {
            $dtoClass = self::ACL_DTO_CLASS;
            $returnsCode = (string) ium_config(SalesConfigEnum::AccountingReturnsCode, '4200');
            $receivablesCode = (string) ium_config(SalesConfigEnum::AccountingReceivablesCode, '1100');
            $autoPost = (bool) ium_config(SalesConfigEnum::AccountingAutoPost, true);

            $dto = $dtoClass::fromArray([
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
                'idempotency_key' => 'sales:rma:credit:' . $rma->id,
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
        if (! $this->isAvailable()) {
            return null;
        }

        $journalEntryId = $order->metadata['accounting_journal_entry_id'] ?? null;
        if (! $journalEntryId) {
            return null;
        }

        try {
            $modelClass = '\Obelaw\Ium\Accounting\Models\JournalEntry';
            if (! class_exists($modelClass)) {
                return null;
            }

            $journalEntry = $modelClass::find((int) $journalEntryId);
            if (! $journalEntry) {
                return null;
            }

            $statusValue = is_object($journalEntry->status) ? $journalEntry->status->value : (string) $journalEntry->status;
            if ($statusValue !== 'posted') {
                return null;
            }

            return ium()->accounting()->journalEntries()->reverse(
                entryId: (int) $journalEntryId,
                reason: sprintf('Order [%s] cancelled: %s', $order->order_number, $reason),
                actorId: 'sales-bridge',
            );
        } catch (Throwable $e) {
            Log::warning(sprintf('SalesAccountingBridge: failed to reverse voucher for order [%s]: %s', $order->order_number, $e->getMessage()), [
                'exception' => $e,
            ]);

            return null;
        }
    }
}
