# Obelawium Sales & Order Management System (Sales / OMS) Domain Module

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-8892BF.svg)](https://php.net)
[![Obelawium](https://img.shields.io/badge/Obelawium-Domain%20Module-orange.svg)](https://github.com/obelawium/ium)

The **Sales & Order Management System (`ium-sales`)** is a headless, domain-driven ERP module within the [Obelawium](https://github.com/obelawium/ium) ecosystem. It orchestrates the entire commercial lifecycle of customer demand: omnichannel order capture, quotation conversion, strict price-lock freeze invariants, stock allocation, multi-facility split fulfillment, and Return Merchandise Authorizations (RMA).

---

## 1. Architectural Philosophy & Fluent DSL Gateway

`ium-sales` operates strictly as an autonomous headless business domain. It contains no presentation layers (no HTTP controllers, routes, Blade, Livewire, or Inertia). All operations flow through the unified fluent gateway:

```php
ium() -> sales() -> {service}() -> {method}()
```

### Quick Example

```php
use Obelaw\Ium\Sales\Data\CreateSalesOrderDto;
use Obelaw\Ium\Sales\Data\SalesOrderLineDto;

// 1. Create a commercial sales order
$order = ium()->sales()->orders()->create(new CreateSalesOrderDto(
    lines: [
        new SalesOrderLineDto(
            sku: 'SRV-001',
            name: 'Cloud Compute Node',
            unitPrice: 350.0,
            quantity: 2.0,
            discountAmount: 20.0,
            taxRate: 14.0,
        ),
    ],
    customerType: 'company',
    customerId: 101,
    channelCode: 'b2b-portal',
    currency: 'USD',
    shippingTotal: 25.0,
));

// 2. Confirm order (Validates financial balance and permanently locks prices)
$order = ium()->sales()->orders()->confirm($order);

// 3. Allocate verified ATP inventory
$order = ium()->sales()->fulfillment()->allocate($order, 'WH-CENTRAL');

// 4. Release fulfillment batch to WMS
$batch = $order->batches()->first();
ium()->sales()->fulfillment()->releaseBatch($batch);
```

---

## 2. Core Domain Invariants ("The Law")

`ium-sales` enforces strict non-negotiable domain invariants:

### Invariant 1: Order Financial Balance Rule
$$\text{Total Order Value} = \sum(\text{Line Prices} - \text{Discounts}) + \text{Taxes} + \text{Shipping Fees}$$
The order total must reconcile exactly with line amounts, discounts, taxes, and shipping fees. Rounding is calculated with high precision. Corrupted orders fail confirmation with `InvalidFinancialBalanceException`.

### Invariant 2: "The Price-Lock Law" (Pricing Immutability)
Once an order transitions to `Confirmed`, its unit prices, line discounts, taxes, shipping fees, currency, and line items are **permanently frozen**. Direct in-place updates or deletions throw `OrderPriceLockedException`. Adjustments must be performed via formal compensating documents (Supplementary Orders, Credit Notes, or RMAs).

### Invariant 3: Stock Allocation Rule
Hard allocation cannot occur without verified Available-To-Promise (ATP) stock at the target sourcing node. Attempting to allocate stock when requested exceeds available throws `InsufficientInventoryException`.

### Invariant 4: Split & Cancellation Governance
Confirmed orders that have entered active warehouse fulfillment (`Released`, `Shipped`) **cannot be cancelled in place**. Attempting to cancel throws `CannotCancelReleasedOrderException`. Cancellation must follow formal goods interception and RMA/credit compensation. Pre-release cancellation is permitted.

### Invariant 5: RMA Quantity Integrity
Return requests cannot exceed eligible returnable quantities:
$$\text{Max Returnable} = \text{Shipped Quantity} - \text{Previously Returned Quantity}$$
Violations throw `InvalidRmaQuantityException`.

---

## 3. Services Overview

| Gateway Method | Service Class | Primary Responsibilities |
| :--- | :--- | :--- |
| `ium()->sales()->orders()` | `OrderService` | Omnichannel order creation, ingestion, confirmation, price-lock, and cancellation governance. |
| `ium()->sales()->quotations()` | `QuotationService` | Drafting, sending, customer acceptance, rejection, expiration, and conversion into orders. |
| `ium()->sales()->fulfillment()` | `FulfillmentService` | ATP allocation, multi-facility split shipment routing, WMS batch release, carrier shipping & delivery tracking. |
| `ium()->sales()->rmas()` | `RmaService` | Return Merchandise Authorizations, goods receipt inspection disposition, and credit compensation. |
| `ium()->sales()->channels()` | `ChannelService` | Omnichannel profiles (`ecommerce`, `mobile`, `b2b`, `pos`, `marketplace`). |
| `ium()->sales()->pricing()` | `PricingEngineService` | Financial balance equation validation, discount tiers, and line-level tax calculation. |
| `ium()->sales()->audit()` | `AuditService` | Immutable append-only forensic audit trail for state transitions, price locks, and cancellations. |
| `ium()->sales()->acl()` | `AclService` | Anti-Corruption Layer for inbound payloads (POS/EDI/Carts) and outbound payloads (WMS/TMS/FMS). |

---

## 4. Domain Events

All domain events are emitted asynchronously across bounded contexts:

- `QuotationAccepted`: Emitted when a customer accepts a quotation.
- `SalesOrderConfirmed`: Emitted when an order is confirmed and its commercial terms are frozen.
- `FulfillmentBatchReleased`: Emitted when a batch is released to WMS for picking/packing.
- `OrderSplitted`: Emitted when an order is partitioned across multiple fulfillment nodes.
- `OrderCancelled`: Emitted when an order is cancelled according to pre-release governance rules.
- `ReturnAuthorized`: Emitted when an RMA request is approved.

---

## 5. Testing

The module includes a comprehensive PestPHP test suite validating all domain invariants:

```bash
composer test
```
