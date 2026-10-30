<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Obelaw\Ium\Accounting\Data\CreateAccountDto;
use Obelaw\Ium\Accounting\Data\CreateFiscalPeriodDto;
use Obelaw\Ium\Accounting\Data\CreateFiscalYearDto;
use Obelaw\Ium\Accounting\Enums\AccountClassification;
use Obelaw\Ium\Accounting\Enums\AccountType;
use Obelaw\Ium\Accounting\Enums\FiscalPeriodStatus;
use Obelaw\Ium\Accounting\Enums\NormalBalance;
use Obelaw\Ium\Accounting\Models\JournalEntry;
use Obelaw\Ium\Accounting\Providers\ObelawAccountingServiceProvider;
use Obelaw\Ium\Sales\Data\CreateQuotationDto;
use Obelaw\Ium\Sales\Data\CreateRmaDto;
use Obelaw\Ium\Sales\Data\CreateSalesOrderDto;
use Obelaw\Ium\Sales\Data\ReceiveRmaGoodsDto;
use Obelaw\Ium\Sales\Data\RecordOrderPaymentDto;
use Obelaw\Ium\Sales\Enums\SalesConfigEnum;
use Obelaw\Ium\Sales\Integrations\Accounting\SalesAccountingBridge;
use Obelaw\Ium\Sales\Models\SalesOrder;

beforeEach(function () {
    // Register Accounting Service Provider
    app()->register(ObelawAccountingServiceProvider::class);

    // Run Accounting Migrations
    $accountingMigrations = realpath(__DIR__ . '/../../../../accounting/ium-accounting/database/migrations');
    if ($accountingMigrations) {
        Artisan::call('migrate', ['--path' => $accountingMigrations, '--realpath' => true]);
    }

    // Set up open fiscal year & periods
    $yearDto = new CreateFiscalYearDto(
        name: 'Fiscal Year 2026',
        startDate: '2026-01-01',
        endDate: '2026-12-31'
    );
    $fy = ium()->accounting()->fiscalPeriods()->createYear($yearDto);

    $periodDto = new CreateFiscalPeriodDto(
        fiscalYearId: $fy->id,
        periodNumber: 10,
        name: 'October 2026',
        startDate: '2026-10-01',
        endDate: '2026-10-31'
    );
    ium()->accounting()->fiscalPeriods()->createPeriod($periodDto);

    // Create Leaf Accounts for integration
    // 1100 - Accounts Receivable (Asset)
    ium()->accounting()->accounts()->create(new CreateAccountDto(
        code: '1100',
        name: 'Accounts Receivable',
        type: AccountType::ASSET,
        normalBalance: NormalBalance::DEBIT,
        classification: AccountClassification::POSTING,
        isLeaf: true
    ));

    // 4000 - Sales Revenue (Revenue)
    ium()->accounting()->accounts()->create(new CreateAccountDto(
        code: '4000',
        name: 'Commercial Sales Revenue',
        type: AccountType::REVENUE,
        normalBalance: NormalBalance::CREDIT,
        classification: AccountClassification::POSTING,
        isLeaf: true
    ));

    // 1010 - Bank Clearing (Asset)
    ium()->accounting()->accounts()->create(new CreateAccountDto(
        code: '1010',
        name: 'Main Operating Bank Account',
        type: AccountType::ASSET,
        normalBalance: NormalBalance::DEBIT,
        classification: AccountClassification::POSTING,
        isLeaf: true
    ));

    // 4200 - Sales Returns & Allowances (Revenue)
    ium()->accounting()->accounts()->create(new CreateAccountDto(
        code: '4200',
        name: 'Sales Returns and Allowances',
        type: AccountType::REVENUE,
        normalBalance: NormalBalance::DEBIT,
        classification: AccountClassification::POSTING,
        isLeaf: true
    ));
});

it('detects accounting domain when registered and enabled', function () {
    $bridge = app(SalesAccountingBridge::class);
    expect($bridge->isAvailable())->toBeTrue();
});

it('automatically posts balanced double-entry vouchers to General Ledger upon order confirmation and payment', function () {
    // 1. Create a Sales Order for $250.00
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

    // 2. Confirm order -> triggers PostOrderInvoiceToAccountingListener
    $order = ium()->sales()->orders()->confirm($order);
    $order->refresh();

    // Verify voucher reference was recorded on order metadata
    $invoiceVoucherId = $order->metadata['accounting_journal_entry_id'] ?? null;
    expect($invoiceVoucherId)->not()->toBeNull();

    // Verify Journal Entry on Accounting General Ledger
    $invoiceEntry = JournalEntry::with('lines.account')->find((int) $invoiceVoucherId);
    expect($invoiceEntry)->not()->toBeNull();
    expect($invoiceEntry->status->value)->toBe('posted');
    expect($invoiceEntry->lines)->toHaveCount(2);

    // Assert Zero-Sum Parity on invoice voucher
    $debitLine = $invoiceEntry->lines->firstWhere('type.value', 'debit');
    $creditLine = $invoiceEntry->lines->firstWhere('type.value', 'credit');
    expect($debitLine->amount)->toBe(25000);
    expect($creditLine->amount)->toBe(25000);
    expect($debitLine->account->code)->toBe('1100'); // AR
    expect($creditLine->account->code)->toBe('4000'); // Revenue

    // 3. Record full customer payment of $250.00
    $payment = ium()->sales()->payments()->recordPayment(RecordOrderPaymentDto::from([
        'sales_order_id' => $order->id,
        'amount' => 250.0,
        'payment_method' => 'bank_wire',
    ]));

    $payment->refresh();
    $paymentVoucherId = $payment->metadata['accounting_journal_entry_id'] ?? null;
    expect($paymentVoucherId)->not()->toBeNull();

    // Verify Payment Journal Entry
    $paymentEntry = JournalEntry::with('lines.account')->find((int) $paymentVoucherId);
    expect($paymentEntry)->not()->toBeNull();
    expect($paymentEntry->status->value)->toBe('posted');

    // Debit Bank (1010), Credit AR (1100)
    $payDebit = $paymentEntry->lines->firstWhere('type.value', 'debit');
    $payCredit = $paymentEntry->lines->firstWhere('type.value', 'credit');
    expect($payDebit->amount)->toBe(25000);
    expect($payCredit->amount)->toBe(25000);
    expect($payDebit->account->code)->toBe('1010'); // Bank
    expect($payCredit->account->code)->toBe('1100'); // AR
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

    // Cancel order
    $order = ium()->sales()->orders()->cancel($order, 'Customer cancellation before shipment');
    expect($order->status->value)->toBe('cancelled');

    // Verify original entry was reversed via Storno
    $originalEntry = JournalEntry::find($originalVoucherId);
    expect($originalEntry->status->value)->toBe('reversed');
    expect($originalEntry->reversal_entry_id)->not()->toBeNull();

    // Verify reversal entry exists and is posted
    $reversalEntry = JournalEntry::find($originalEntry->reversal_entry_id);
    expect($reversalEntry->status->value)->toBe('posted');
});

it('automatically posts credit note voucher on GL when customer return RMA is approved for credit', function () {
    // 1. Create, confirm, allocate and ship an order
    $order = ium()->sales()->orders()->create(new CreateSalesOrderDto(
        lines: [
            new \Obelaw\Ium\Sales\Data\SalesOrderLineDto(sku: 'RMA-PHONE', name: 'Smart Phone', unitPrice: 500.0, quantity: 2.0),
        ],
    ));
    $order = ium()->sales()->orders()->confirm($order);
    $order = ium()->sales()->fulfillment()->allocate($order);
    $batch = $order->batches()->first();
    $batch = ium()->sales()->fulfillment()->releaseBatch($batch);
    ium()->sales()->fulfillment()->markBatchShipped($batch, new \Obelaw\Ium\Sales\Data\MarkShippedDto());

    $line = $order->lines()->first();

    // 2. Request and Authorize RMA
    $rma = ium()->sales()->rmas()->request(new CreateRmaDto(
        salesOrderId: $order->id,
        reasonCode: 'DEFECTIVE',
        lines: [new \Obelaw\Ium\Sales\Data\RmaLineDto(salesOrderLineId: $line->id, quantityRequested: 1.0)],
    ));
    $rma = ium()->sales()->rmas()->authorize($rma);

    // 3. Receive Goods
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

    // 4. Approve Credit -> Triggers PostRmaCreditToAccountingListener
    $rma = ium()->sales()->rmas()->approveCredit($rma);
    $rma->refresh();

    // Verify voucher id in RMA metadata
    $creditVoucherId = $rma->metadata['accounting_journal_entry_id'] ?? null;
    expect($creditVoucherId)->not()->toBeNull();

    // Verify Journal Entry on Accounting General Ledger
    $creditEntry = JournalEntry::with('lines.account')->find((int) $creditVoucherId);
    expect($creditEntry)->not()->toBeNull();
    expect($creditEntry->status->value)->toBe('posted');

    // Debit Returns (4200), Credit AR (1100)
    $debitLine = $creditEntry->lines->firstWhere('type.value', 'debit');
    $creditLine = $creditEntry->lines->firstWhere('type.value', 'credit');
    expect($debitLine->amount)->toBe(50000); // 1 unit * $500 = $500.00 = 50,000 cents
    expect($creditLine->amount)->toBe(50000);
    expect($debitLine->account->code)->toBe('4200'); // Returns & Allowances
    expect($creditLine->account->code)->toBe('1100'); // AR
});
