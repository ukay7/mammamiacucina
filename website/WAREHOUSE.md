# Warehouse order workflow — 29 September 2026

Implemented locally. No live deployment or Git push performed in this task. Existing live data must be preserved.

## Flow
- New Quick Sale/POS orders have status Completed (both pickup and delivery). Payment status stays independent; an unpaid POS delivery still has a balance to collect. Historical POS statuses are not rewritten.
- Website cash orders start Placed. Hosted-payment orders must be verified paid before warehouse transfer.
- Admin: View Orders → open order → Send to Warehouse.
- Warehouse: View Orders → item checklist + per-item notes → Save Packing Progress, Return to Admin — Issue, or All Packed — Ready for Dispatch.
- Every item must be checked before Ready. A return needs an explanation. Checked items turn green immediately; Save Progress persists them.
- Returned orders have a red warning in the list and dashboard. Admin can edit or resolve the problem, then send back. A new round resets the checklist and current notes; earlier notes remain in Order History.
- Admin advances Ready for dispatch → Out for Delivery → Delivered → Completed. Pickup can go from Ready to Delivered (collected). Completion requires settled payment.
- Legacy Confirmed / Preparing statuses still work for existing orders.

## Permissions
Additive migration creates the Warehouse User type with dashboard.view and warehouse.pack. Assign a staff account to it using User Management. No credentials or staff accounts are automatically created.
Warehouse users can read/print only website orders currently sent to warehouse, packing, or ready. Packing slips contain no prices. They cannot amend orders, change payment state, or access payment reports. Super Admin retains packing rights.

## Editing and money
Admin can add/remove products, change quantities and unit prices, customer/address, fulfillment, delivery charge and tax before dispatch/completion. Quantity zero removes a line; an order must keep at least one item. Tracked inventory changes only by the quantity difference. Insufficient stock and stale edits are rejected atomically. Pickup forces delivery charge to zero. Tax can be manually set or recalculated on the revised subtotal using the order's stored rate (current settings for older orders).
Edits during packing/ready return the order for admin review and a new packing check. Dispatched, delivered, completed and cancelled items cannot be rewritten; physical returns need separate handling.
Paid order edits never rewrite the original capture. A difference is displayed as amount due or refund due. Record external collections/refunds only after money moved; these controls do not charge a gateway. Original Stripe/PayPal refunds use the provider and verified reconciliation/webhooks. PayPal partial refunds require its refund webhook; do not claim a local ledger entry sends a PayPal refund.
Reports use original receipts plus signed adjustment entries, not the revised total as received money. Filters/counts use original order method; adjustment money groups under the actual collection method. Order dates still define report range. Original provider captures are not edited.

## Files
- app/Services/WarehousePacking.php: packing checks and transitions, revision checks, audit history.
- app/Services/OrderAmendment.php: item/customer/fulfillment changes, inventory differences, external collection ledger.
- app/Services/OrderManagement.php: admin transitions, resend/reset, cancellation and payment baselines.
- app/Models/Order.php and OrderSettlement.php: statuses, original money received and balance.
- app/Http/Controllers/Admin/OrderController.php: permission-scoped queue, packing/amend/settlement endpoints.
- resources/views/admin/orders/{packing,warehouse,packing-print,amend,settlements,dashboard}.blade.php and index/show.
- PaymentReportController.php and reports/payments.blade.php: actual receipt/refund accounting.
- migrations 2026_09_29_140000_warehouse_order_workflow and 140100_order_refund_baseline, both applied locally.
- tests/Feature/WarehouseWorkflowTest.php: packing cycle, permission boundaries, stock rollback, amendments, payment/refund balances and pending gateway protection.

## Validation
Full regression suite: 148 tests, 1591 assertions passed before the last refund edge-case additions. Then 36 order/warehouse/gateway tests, 473 assertions passed including four extra edge-case tests. Desktop and 390px mobile previews render without horizontal overflow or JavaScript errors; checkbox green state verified. No real orders were changed for previews.

## Local demo
http://127.0.0.1:18088/admin/orders
Dashboard shows warehouse issues and packing queues. The local server uses website/public as its working directory.
Fictional screenshots: ../.tools/warehouse-desktop.png and ../.tools/warehouse-admin-desktop.png. Preview fixtures are ignored scratch files, not public auth bypasses.

## Own sales visibility update
The warehouse queue also includes orders created by the logged-in warehouse user (created_by), including Completed POS sales. Own POS orders have read-only details and printable sales receipts. Other staff's sales are excluded unless the order is a website order currently parked for warehouse packing. Search, dates and status filters apply to both sets. Validated with 25 access/workflow tests (245 assertions).

## Automatic routing update
Website cash orders now go directly to Sent to warehouse. Online-paid website orders go there after verified payment; unconfirmed or late payments cannot enter packing. Initial manual admin transfer is no longer required. Returned problem orders still need admin review and resend. This supersedes the earlier Placed-first description.
