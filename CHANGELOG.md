# Changelog

All notable changes to `obelaw/ium-sales` will be documented in this file.

## v1.0.0

### Added
- Registered `sales` module with `Ium::registerModule()` (`ModuleScope::Tenant`) and full multi-tenant conformance tests (`SalesConformanceTest`).
- Added `OrderStatus::Shipped` state for fully shipped orders prior to delivery completion.
- Added multi-line General Ledger invoice posting in `SalesAccountingBridge` separating Sales Revenue (`4000`), Tax Payable (`2200`), and Shipping Revenue (`4100`) using `obelaw/ium-accounting` v1.0 `AclIngestData` with idempotency keys.
- Added `Money::fromMajor()` ISO 4217 minor-unit precision across `SalesOrder`, `SalesOrderLine`, `PaymentService`, and `SalesAccountingBridge` (supports 0, 2, and 3 decimal currencies).

### Fixed
- Fixed `SalesDomain::$config` property visibility conflict with `Obelaw\Ium\Abstracts\Domain`.
- Fixed broken ternary in `FulfillmentService::markBatchShipped()` so fully shipped orders transition to `OrderStatus::Shipped`.
- Prevented concurrent RMA double-returns in `RmaService::request()` by subtracting in-flight open RMA quantities under pessimistic row lock.
- Added strict receipt bounds validation (`0 <= quantity_received <= quantity_requested`) and proportional tax-inclusive refund recalculation in `RmaService::receiveGoods()`.
- Switched all service transactions to `Ium::connection()->transaction()` with `afterCommit()` event dispatch for tenant safety.
- Updated `SalesAccountingBridge::reverseOrderInvoice()` parameter order for `obelaw/ium-accounting` v1.0 `JournalEntryGateway::reverse()`.
- Removed hardcoded relative accounting autoload path and dev-only `.agents/` artifacts for clean Packagist distribution.
