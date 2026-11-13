# Delivery documents

Pending and Completed Orders, warehouse cards, and order details have two per-order actions:

- **Delivery Print** opens landscape #10 / COM10 stationery inspired by the approved reference. The exact PDF media box is 684 x 297 points (9.5 x 4.125 inches). Choose Actual size / 100% when printing and disable browser headers/footers. The envelope is an explicit user-approved exception to the A4 rule.
- **Delivery Form** opens an A4 delivery document with configured company details, customer identity, customer/profile address, the saved order destination, Toronto delivery appointment, courier/route, order reference/date/status/source, product details, customer instructions, and a fulfillment-specific handover table. The product columns are Serial No., Photo, Item code, Premium Marketing Name, Net carton weight (kg), Pack Weight (kg), Pieces per carton and Qty. Photos are compact and serial numbers start at 1 for each form. No product prices, totals, charges, discounts or balances are printed. Free-text customer instructions are printed as entered; internal warehouse/payment/audit notes are not included.

The handover replaces the standalone receiver-name/signature lines. Delivery orders have the approved 10-step record from order creation through payment handover; pickup orders have the 4-step record ending with Received by. Dates, times, checkboxes, staff/recipient details and confirmations are blank for handwriting. As explicitly requested on 14 November 2026, the delivery version includes blank CAD$, Cash and Cheque fields; it never fills an amount or confirms a payment automatically. The handover and document footer stay together when a long product list spans pages.

Both offer Print and Download PDF. Company details and GST/HST come from General Settings; GST/HST remains at bottom right. HTML and PDFs are private/no-store, use the existing order permissions and warehouse visibility scope, and do not modify orders, invoices, payments, stock, email history or document snapshots. Deleted orders are unavailable. Pickup orders explicitly use their saved pickup address instead of inventing a delivery destination.

The delivery destination and contact data come from the order, so changing the customer's company or alternate address does not redirect an existing order's print. Customer business identity comes from its linked profile. Item codes and quantities come from the saved order lines. Premium Marketing Name, pack weight and photos use the current linked product; net carton weight and pieces per carton use its Product DNA fields. A missing product/name falls back to the saved order-line name. Missing weights/counts show a dash; pack piece counts and unpublished pricing inputs are not substituted for carton data. Existing invoices and their snapshots are untouched.

No migration, package installation, environment change or email-outbox run is needed. Clear/rebuild compiled views when deploying and keep PHP GD enabled for PDF images.

Paper-size reference: https://learn.microsoft.com/en-us/windows/win32/intl/paper-sizes
