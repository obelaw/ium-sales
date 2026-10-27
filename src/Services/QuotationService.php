<?php

declare(strict_types=1);

namespace Obelaw\Ium\Sales\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Obelaw\Ium\Sales\Data\CreateQuotationDto;
use Obelaw\Ium\Sales\Data\CreateSalesOrderDto;
use Obelaw\Ium\Sales\Data\SalesOrderLineDto;
use Obelaw\Ium\Sales\Enums\QuotationStatus;
use Obelaw\Ium\Sales\Enums\SalesConfigEnum;
use Obelaw\Ium\Sales\Events\QuotationAccepted;
use Obelaw\Ium\Sales\Exceptions\InvalidOrderStateTransitionException;
use Obelaw\Ium\Sales\Exceptions\QuotationExpiredException;
use Obelaw\Ium\Sales\Models\Quotation;
use Obelaw\Ium\Sales\Models\QuotationLine;
use Obelaw\Ium\Sales\Models\SalesOrder;

/**
 * Commercial Quotation & Proforma Management Service.
 */
final class QuotationService
{
    public function __construct(
        private readonly PricingEngineService $pricing,
        private readonly AuditService $audit,
    ) {}

    /**
     * Create a new draft quotation.
     */
    public function create(CreateQuotationDto $dto): Quotation
    {
        return DB::transaction(function () use ($dto): Quotation {
            $code = $dto->code ?? $this->generateQuotationCode();

            $lineInputs = array_map(fn($line) => [
                'unit_price' => $line->unitPrice,
                'quantity' => $line->quantity,
                'discount_amount' => $line->discountAmount,
                'tax_rate' => $line->taxRate,
            ], $dto->lines);

            $calc = $this->pricing->computeOrderFinancials($lineInputs, $dto->shippingTotal);

            /** @var Quotation $quotation */
            $quotation = Quotation::create([
                'code' => $code,
                'customer_type' => $dto->customerType,
                'customer_id' => $dto->customerId,
                'channel_code' => $dto->channelCode,
                'currency' => $dto->currency,
                'status' => QuotationStatus::Draft,
                'valid_until' => $dto->validUntil ? Carbon::parse($dto->validUntil) : now()->addDays(30),
                'subtotal' => $calc['subtotal'],
                'discount_total' => $calc['discount_total'],
                'tax_total' => $calc['tax_total'],
                'shipping_total' => $calc['shipping_total'],
                'grand_total' => $calc['grand_total'],
                'notes' => $dto->notes,
                'metadata' => $dto->metadata,
            ]);

            $lineNumber = 1;
            foreach ($dto->lines as $index => $lineDto) {
                $lineCalc = $calc['lines'][$index];

                QuotationLine::create([
                    'quotation_id' => $quotation->id,
                    'line_number' => $lineNumber++,
                    'orderable_type' => $lineDto->orderableType,
                    'orderable_id' => $lineDto->orderableId,
                    'sku' => $lineDto->sku,
                    'name' => $lineDto->name,
                    'unit_price' => $lineDto->unitPrice,
                    'quantity' => $lineDto->quantity,
                    'discount_amount' => $lineCalc['discount'],
                    'tax_rate' => $lineDto->taxRate,
                    'tax_amount' => $lineCalc['tax'],
                    'total_amount' => $lineCalc['total'],
                    'metadata' => $lineDto->metadata,
                ]);
            }

            $this->audit->log(
                auditable: $quotation,
                action: 'created',
                originChannel: $quotation->channel_code,
                after: ['status' => QuotationStatus::Draft->value, 'grand_total' => $quotation->grand_total]
            );

            return $quotation->load('lines');
        });
    }

    /**
     * Mark quotation as sent to customer.
     */
    public function send(Quotation $quotation): Quotation
    {
        if ($quotation->status !== QuotationStatus::Draft) {
            throw InvalidOrderStateTransitionException::transition('Quotation', $quotation->status->value, QuotationStatus::Sent->value);
        }

        $before = ['status' => $quotation->status->value];
        $quotation->update(['status' => QuotationStatus::Sent]);

        $this->audit->log(
            auditable: $quotation,
            action: 'sent',
            originChannel: $quotation->channel_code,
            before: $before,
            after: ['status' => QuotationStatus::Sent->value]
        );

        return $quotation;
    }

    /**
     * Customer acceptance of quotation. Emits QuotationAccepted.
     */
    public function accept(Quotation $quotation): Quotation
    {
        if ($quotation->isExpired()) {
            throw QuotationExpiredException::forQuotation($quotation->code, (string) $quotation->valid_until);
        }

        if (!in_array($quotation->status, [QuotationStatus::Draft, QuotationStatus::Sent], true)) {
            throw InvalidOrderStateTransitionException::transition('Quotation', $quotation->status->value, QuotationStatus::Accepted->value);
        }

        $before = ['status' => $quotation->status->value];
        $quotation->update(['status' => QuotationStatus::Accepted]);

        $this->audit->log(
            auditable: $quotation,
            action: 'accepted',
            originChannel: $quotation->channel_code,
            before: $before,
            after: ['status' => QuotationStatus::Accepted->value]
        );

        event(new QuotationAccepted($quotation));

        if (ium_config(SalesConfigEnum::AutoConfirm, false)) {
            $this->convertToOrder($quotation);
        }

        return $quotation;
    }

    /**
     * Customer rejection of quotation.
     */
    public function reject(Quotation $quotation, ?string $reason = null): Quotation
    {
        if (!in_array($quotation->status, [QuotationStatus::Draft, QuotationStatus::Sent], true)) {
            throw InvalidOrderStateTransitionException::transition('Quotation', $quotation->status->value, QuotationStatus::Rejected->value);
        }

        $before = ['status' => $quotation->status->value];
        $quotation->update(['status' => QuotationStatus::Rejected]);

        $this->audit->log(
            auditable: $quotation,
            action: 'rejected',
            originChannel: $quotation->channel_code,
            before: $before,
            after: ['status' => QuotationStatus::Rejected->value],
            reasonCode: $reason
        );

        return $quotation;
    }

    /**
     * Expire a quotation whose validity has elapsed.
     */
    public function expire(Quotation $quotation): Quotation
    {
        $before = ['status' => $quotation->status->value];
        $quotation->update(['status' => QuotationStatus::Expired]);

        $this->audit->log(
            auditable: $quotation,
            action: 'expired',
            originChannel: $quotation->channel_code,
            before: $before,
            after: ['status' => QuotationStatus::Expired->value]
        );

        return $quotation;
    }

    /**
     * Convert an accepted quotation into a binding Sales Order.
     */
    public function convertToOrder(Quotation $quotation): SalesOrder
    {
        if ($quotation->status !== QuotationStatus::Accepted) {
            throw InvalidOrderStateTransitionException::transition('Quotation', $quotation->status->value, 'ConvertedToOrder');
        }

        return DB::transaction(function () use ($quotation): SalesOrder {
            $quotation->loadMissing('lines');

            $orderLines = [];
            foreach ($quotation->lines as $line) {
                $orderLines[] = new SalesOrderLineDto(
                    sku: $line->sku,
                    name: $line->name,
                    unitPrice: (float) $line->unit_price,
                    quantity: (float) $line->quantity,
                    discountAmount: (float) $line->discount_amount,
                    taxRate: (float) $line->tax_rate,
                    orderableType: $line->orderable_type,
                    orderableId: $line->orderable_id,
                    metadata: $line->metadata ?? [],
                );
            }

            $orderDto = new CreateSalesOrderDto(
                lines: $orderLines,
                orderNumber: null,
                customerType: $quotation->customer_type,
                customerId: $quotation->customer_id,
                channelCode: $quotation->channel_code,
                currency: $quotation->currency,
                shippingTotal: (float) $quotation->shipping_total,
                quotationId: $quotation->id,
                metadata: ['converted_from_quotation' => $quotation->code],
            );

            /** @var OrderService $orderService */
            $orderService = app(OrderService::class);
            $order = $orderService->create($orderDto);

            $quotation->update(['status' => QuotationStatus::Converted]);

            $this->audit->log(
                auditable: $quotation,
                action: 'converted_to_order',
                originChannel: $quotation->channel_code,
                after: ['sales_order_id' => $order->id, 'order_number' => $order->order_number]
            );

            return $order;
        });
    }

    private function generateQuotationCode(): string
    {
        return 'QT-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    }
}
