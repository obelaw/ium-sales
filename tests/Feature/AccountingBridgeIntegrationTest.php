<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Obelaw\Ium\Accounting\Data\CreateAccountData;
use Obelaw\Ium\Accounting\Data\CreateFiscalYearData;
use Obelaw\Ium\Accounting\Data\CreateJournalData;
use Obelaw\Ium\Accounting\Enums\AccountClassification;
use Obelaw\Ium\Accounting\Enums\AccountType;
use Obelaw\Ium\Accounting\Enums\JournalType;
use Obelaw\Ium\Accounting\Enums\NormalBalance;
use Obelaw\Ium\Accounting\Models\JournalEntry;
use Obelaw\Ium\Accounting\Providers\ObelawAccountingServiceProvider;
use Obelaw\Ium\Sales\Data\CreateRmaDto;
use Obelaw\Ium\Sales\Data\CreateSalesOrderDto;
use Obelaw\Ium\Sales\Data\MarkShippedDto;
use Obelaw\Ium\Sales\Data\ReceiveRmaGoodsDto;
use Obelaw\Ium\Sales\Data\RecordOrderPaymentDto;
use Obelaw\Ium\Sales\Data\RmaLineDto;
use Obelaw\Ium\Sales\Data\SalesOrderLineDto;
use Obelaw\Ium\Sales\Integrations\Accounting\SalesAccountingBridge;

beforeEach(function () {
    if (! class_exists(ObelawAccountingServiceProvider::class)) {
        test()->markTestSkipped('obelaw/ium-accounting is not installed.');
    }

    app()->register(ObelawAccountingServiceProvider::class);

    foreach (\Obelaw\Ium\Facades\Ium::modules()->get('accounting')->migrationPaths as $path) {
        if (is_dir($path)) {
            Artisan::call('migrate', ['--path' => $path, '--realpath' => true]);
        }
    }

    $year = date('Y');
    ium()->accounting()->fiscalPeriods()->createYear(new CreateFiscalYearData(
        name: "Fiscal Year {$year}",
        startDate: "{$year}-01-01",
        endDate: "{$year}-12-31",
        generateMonthlyPeriods: true,
    ));

    ium()->accounting()->journals()->create(new CreateJournalData(
        code: 'GJ',
        name: 'General Journal',
        type: JournalType::GENERAL,
    ));

    // 1100 - Accounts Receivable (Asset)
    ium()->accounting()->accounts()->create(new CreateAccountData(
        code: '1100',
        name: 'Accounts Receivable',
        type: AccountType::ASSET,
        classification: AccountClassification::POSTING,
        normalBalance: NormalBalance::DEBIT,
    ));

    // 4000 - Sales Revenue (Revenue)
    ium()->accounting()->accounts()->create(new CreateAccountData(
        code: '4000',
        name: 'Commercial Sales Revenue',
        type: AccountType::REVENUE,
        classification: AccountClassification::POSTING,
        normalBalance: NormalBalance::CREDIT,
    ));

    // 2200 - Tax Payable (Liability)
    ium()->accounting()->accounts()->create(new CreateAccountData(
        code: '2200',
        name: 'Sales Tax Payable',
        type: AccountType::LIABILITY,
        classification: AccountClassification::POSTING,
        normalBalance: NormalBalance::CREDIT,
    ));

    // 4100 - Shipping Revenue (Revenue)
    ium()->accounting()->accounts()->create(new CreateAccountData(
        code: '4100',
        name: 'Shipping & Handling Revenue',
        type: AccountType::REVENUE,
        classification: AccountClassification::POSTING,
        normalBalance: NormalBalance::CREDIT,
    ));

    // 1010 - Bank Clearing (Asset)
    ium()->accounting()->accounts()->create(new CreateAccountData(
        code: '1010',
        name: 'Main Operating Bank Account',
        type: AccountType::ASSET,
        classification: AccountClassification::POSTING,
        normalBalance: NormalBalance::DEBIT,
    ));

    // 4200 - Sales Returns & Allowances (Revenue)
    ium()->accounting()->accounts()->create(new CreateAccountData(
        code: '4200',
        name: 'Sales Returns and Allowances',
        type: AccountType::REVENUE,
        classification: AccountClassification::POSTING,
        normalBalance: NormalBalance::DEBIT,
    ));
});

it('detects accounting domain when registered and enabled', function () {
    $bridge = app(SalesAccountingBridge::class);
    expect($bridge->isAvailable())->toBeTrue();
});

it('automatically posts balanced double-entry vouchers to General Ledger upon order confirmation and payment', function () {
    $order = ium()->sales()->orders()->create(CreateSalesOrderDto::from([
        'order_number' => 'SO-ACC-001',
        'customer_id' => 'CUST-GL-01',
        'currency' => 'USD',
        'lines' => [
            [
                'sku' => 'MONITOR-4K',
                'name' => 'Ultra HD 4K Monitor',
                'unit_price' => 250.0,
                'quantity' => 1.0,
            ],
        ],
    ]));

    expect($order->grand_total)->toEqual(250.0);
    expect($order->grand_total_minor)->toBe(25000);

    $order = ium()->sales()->orders()->confirm($order);
    $order->refresh();

    $invoiceVoucherId = $order->metadata['accounting_journal_entry_id'] ?? null;
    expect($invoiceVoucherId)->not()->toBeNull();

    $invoiceEntry = JournalEntry::with('lines.account')->find((int) $invoiceVoucherId);
    expect($invoiceEntry)->not()->toBeNull();
    expect($invoiceEntry->status->value)->toBe('posted');
    expect($invoiceEntry->lines)->toHaveCount(2);

    $debitLine = $invoiceEntry->lines->firstWhere('type.value', 'debit');
    $creditLine = $invoiceEntry->lines->firstWhere('type.value', 'credit');
    expect($debitLine->amount)->toBe(25000);
    expect($creditLine->amount)->toBe(25000);
    expect($debitLine->account->code)->toBe('1100');
    expect($creditLine->account->code)->toBe('4000');

    $payment = ium()->sales()->payments()->recordPayment(RecordOrderPaymentDto::from([
        'sales_order_id' => $order->id,
        'amount' => 250.0,
        'payment_method' => 'bank_wire',
    ]));

    $payment->refresh();
    $paymentVoucherId = $payment->metadata['accounting_journal_entry_id'] ?? null;
    expect($paymentVoucherId)->not()->toBeNull();

    $paymentEntry = JournalEntry::with('lines.account')->find((int) $paymentVoucherId);
    expect($paymentEntry)->not()->toBeNull();
    expect($paymentEntry->status->value)->toBe('posted');

    $payDebit = $paymentEntry->lines->firstWhere('type.value', 'debit');
    $payCredit = $paymentEntry->lines->firstWhere('type.value', 'credit');
    expect($payDebit->amount)->toBe(25000);
    expect($payCredit->amount)->toBe(25000);
    expect($payDebit->account->code)->toBe('1010');
    expect($payCredit->account->code)->toBe('1100');
});

it('splits invoice postings across Revenue (4000), Tax Payable (2200), and Shipping Revenue (4100)', function () {
    $order = ium()->sales()->orders()->create(CreateSalesOrderDto::from([
        'order_number' => 'SO-SPLIT-GL-001',
        'customer_id' => 'CUST-SPLIT-01',
        'currency' => 'USD',
        'shipping_total' => 25.0,
        'lines' => [
            [
                'sku' => 'SERVER-01',
                'name' => 'Rack Server',
                'unit_price' => 1000.0,
                'quantity' => 1.0,
                'discount_amount' => 100.0,
                'tax_rate' => 10.0,
            ],
        ],
    ]));

    // Net revenue = 900.00 (90000), Tax = 90.00 (9000), Shipping = 25.00 (2500), Grand Total = 1015.00 (101500)
    $order = ium()->sales()->orders()->confirm($order);
    $order->refresh();

    $entryId = (int) ($order->metadata['accounting_journal_entry_id'] ?? 0);
    expect($entryId)->toBeGreaterThan(0);

    $entry = JournalEntry::with('lines.account')->findOrFail($entryId);
    expect($entry->status->value)->toBe('posted');
    expect($entry->lines)->toHaveCount(4);

    $byCode = $entry->lines->keyBy(fn ($l) => $l->account->code);
    expect($byCode['1100']->type->value)->toBe('debit');
    expect($byCode['1100']->amount)->toBe(101500);
    expect($byCode['4000']->type->value)->toBe('credit');
    expect($byCode['4000']->amount)->toBe(90000);
    expect($byCode['2200']->type->value)->toBe('credit');
    expect($byCode['2200']->amount)->toBe(9000);
    expect($byCode['4100']->type->value)->toBe('credit');
    expect($byCode['4100']->amount)->toBe(2500);
});

it('automatically posts Storno reversal voucher on GL when confirmed order is cancelled', function () {
    $order = ium()->sales()->orders()->create(CreateSalesOrderDto::from([
        'order_number' => 'SO-REV-001',
        'customer_id' => 'CUST-REV-01',
        'lines' => [
            [
                'sku' => 'TABLET-01',
                'name' => 'Compact Tablet',
                'unit_price' => 100.0,
                'quantity' => 1.0,
            ],
        ],
    ]));

    $order = ium()->sales()->orders()->confirm($order);
    $order->refresh();

    $originalVoucherId = (int) $order->metadata['accounting_journal_entry_id'];
    expect($originalVoucherId)->toBeGreaterThan(0);

    $order = ium()->sales()->orders()->cancel($order, 'Customer cancellation before shipment');
    expect($order->status->value)->toBe('cancelled');

    $originalEntry = JournalEntry::find($originalVoucherId);
    expect($originalEntry->status->value)->toBe('reversed');
    expect($originalEntry->reversal_entry_id)->not()->toBeNull();

    $reversalEntry = JournalEntry::find($originalEntry->reversal_entry_id);
    expect($reversalEntry->status->value)->toBe('posted');
});

it('automatically posts credit note voucher on GL when customer return RMA is approved for credit', function () {
    $order = ium()->sales()->orders()->create(new CreateSalesOrderDto(
        lines: [
            new SalesOrderLineDto(sku: 'RMA-PHONE', name: 'Smart Phone', unitPrice: 500.0, quantity: 2.0),
        ],
    ));
    $order = ium()->sales()->orders()->confirm($order);
    $order = ium()->sales()->fulfillment()->allocate($order);
    $batch = $order->batches()->first();
    $batch = ium()->sales()->fulfillment()->releaseBatch($batch);
    ium()->sales()->fulfillment()->markBatchShipped($batch, new MarkShippedDto());

    $line = $order->lines()->first();

    $rma = ium()->sales()->rmas()->request(new CreateRmaDto(
        salesOrderId: $order->id,
        reasonCode: 'DEFECTIVE',
        lines: [new RmaLineDto(salesOrderLineId: $line->id, quantityRequested: 1.0)],
    ));
    $rma = ium()->sales()->rmas()->authorize($rma);

    $rmaLine = $rma->lines()->first();
    $rma = ium()->sales()->rmas()->receiveGoods($rma, new ReceiveRmaGoodsDto(
        items: [
            [
                'rma_line_id' => $rmaLine->id,
                'quantity_received' => 1.0,
                'disposition' => 'restock',
            ],
        ]
    ));

    $rma = ium()->sales()->rmas()->approveCredit($rma);
    $rma->refresh();

    $creditVoucherId = $rma->metadata['accounting_journal_entry_id'] ?? null;
    expect($creditVoucherId)->not()->toBeNull();

    $creditEntry = JournalEntry::with('lines.account')->find((int) $creditVoucherId);
    expect($creditEntry)->not()->toBeNull();
    expect($creditEntry->status->value)->toBe('posted');

    $debitLine = $creditEntry->lines->firstWhere('type.value', 'debit');
    $creditLine = $creditEntry->lines->firstWhere('type.value', 'credit');
    expect($debitLine->amount)->toBe(50000);
    expect($creditLine->amount)->toBe(50000);
    expect($debitLine->account->code)->toBe('4200');
    expect($creditLine->account->code)->toBe('1100');
});
