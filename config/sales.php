<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Sales & Order Management Domain Configuration
    |--------------------------------------------------------------------------
    */

    // Default commercial currency for orders and quotations
    'default_currency' => env('IUM_SALES_DEFAULT_CURRENCY', 'USD'),

    // Whether quotations automatically convert and confirm when accepted
    'auto_confirm' => env('IUM_SALES_AUTO_CONFIRM', false),

    // Invariant: Enforce immutable price-lock strictly once order is confirmed
    'price_lock_strict' => env('IUM_SALES_PRICE_LOCK_STRICT', true),

    // Enable immutable append-only forensic audit trail
    'audit_enabled' => env('IUM_SALES_AUDIT_ENABLED', true),

    // Default fulfillment routing policy: 'single_node', 'proximity', 'stock_priority', 'cost_optimized'
    'routing_policy' => env('IUM_SALES_ROUTING_POLICY', 'single_node'),

    // Allow splitting orders across multiple fulfillment batches / sourcing nodes
    'allow_split_shipments' => env('IUM_SALES_ALLOW_SPLIT_SHIPMENTS', true),

    // Allow backorders when stock is insufficient during order confirmation
    'allow_backorders' => env('IUM_SALES_ALLOW_BACKORDERS', false),

    // Default return policy window in days for RMA validation
    'rma_window_days' => env('IUM_SALES_RMA_WINDOW_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Optional Accounting (FMS) Integration Configuration
    |--------------------------------------------------------------------------
    */
    'accounting' => [
        // Mode: 'auto' (detects if ium()->hasDomain('accounting')), true, or false
        'enabled' => env('IUM_SALES_ACCOUNTING_ENABLED', 'auto'),
        'auto_post' => env('IUM_SALES_ACCOUNTING_AUTO_POST', true),
        'accounts' => [
            'receivables' => env('IUM_SALES_ACC_RECEIVABLES', '1100'),
            'revenue'     => env('IUM_SALES_ACC_REVENUE', '4000'),
            'tax_payable' => env('IUM_SALES_ACC_TAX', '2200'),
            'shipping'    => env('IUM_SALES_ACC_SHIPPING', '4100'),
            'bank'        => env('IUM_SALES_ACC_BANK', '1010'),
            'cogs'        => env('IUM_SALES_ACC_COGS', '5000'),
            'inventory'   => env('IUM_SALES_ACC_INVENTORY', '1200'),
            'returns'     => env('IUM_SALES_ACC_RETURNS', '4200'),
        ],
    ],
];
