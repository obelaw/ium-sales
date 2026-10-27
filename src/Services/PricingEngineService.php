<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Services;

use Obelaw\Ium\Sales\Exceptions\InvalidFinancialBalanceException;

/**
 * Commercial Pricing & Financial Balance Engine.
 *
 * Enforces Domain Invariant 1 ("Order Financial Balance Rule"):
 * Total Order Value = sum(Line Prices - Discounts) + Taxes + Shipping Fees
 */
final class PricingEngineService
{
    /**
     * Compute a single line amount after discount and tax.
     *
     * @return array{subtotal: float, discount: float, tax: float, total: float}
     */
    public function calculateLineTotals(float $unitPrice, float $quantity, float $discountAmount = 0.0, float $taxRate = 0.0): array
    {
        $subtotal = round($unitPrice * $quantity, 4);
        $discount = round(min($discountAmount, $subtotal), 4);
        $taxable = max(0.0, $subtotal - $discount);
        $tax = round($taxable * ($taxRate / 100.0), 4);
        $total = round($taxable + $tax, 4);

        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'tax' => $tax,
            'total' => $total,
        ];
    }

    /**
     * Reconcile and calculate complete order totals from line collections.
     *
     * @param array<int, array{unit_price: float, quantity: float, discount_amount?: float, tax_rate?: float}> $lines
     * @return array{subtotal: float, discount_total: float, tax_total: float, shipping_total: float, grand_total: float, lines: array<int, array<string, float>>}
     */
    public function computeOrderFinancials(array $lines, float $shippingTotal = 0.0): array
    {
        $subtotal = 0.0;
        $discountTotal = 0.0;
        $taxTotal = 0.0;
        $calculatedLines = [];

        foreach ($lines as $line) {
            $uPrice = (float) ($line['unit_price'] ?? 0.0);
            $qty = (float) ($line['quantity'] ?? 1.0);
            $disc = (float) ($line['discount_amount'] ?? 0.0);
            $taxRate = (float) ($line['tax_rate'] ?? 0.0);

            $calc = $this->calculateLineTotals($uPrice, $qty, $disc, $taxRate);
            $subtotal += $calc['subtotal'];
            $discountTotal += $calc['discount'];
            $taxTotal += $calc['tax'];

            $calculatedLines[] = $calc;
        }

        $subtotal = round($subtotal, 4);
        $discountTotal = round($discountTotal, 4);
        $taxTotal = round($taxTotal, 4);
        $shipping = round(max(0.0, $shippingTotal), 4);

        $grandTotal = round(($subtotal - $discountTotal) + $taxTotal + $shipping, 4);

        return [
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'tax_total' => $taxTotal,
            'shipping_total' => $shipping,
            'grand_total' => $grandTotal,
            'lines' => $calculatedLines,
        ];
    }

    /**
     * Validate that a given grand total adheres strictly to the financial balance rule.
     *
     * @throws InvalidFinancialBalanceException
     */
    public function assertFinancialBalance(
        float $subtotal,
        float $discountTotal,
        float $taxTotal,
        float $shippingTotal,
        float $declaredGrandTotal,
        string $reference = ''
    ): void {
        $expectedGrandTotal = round(($subtotal - $discountTotal) + $taxTotal + $shippingTotal, 4);
        $declared = round($declaredGrandTotal, 4);

        if (abs($expectedGrandTotal - $declared) > 0.01) {
            throw InvalidFinancialBalanceException::forDiscrepancy($expectedGrandTotal, $declared, $reference);
        }
    }
}
