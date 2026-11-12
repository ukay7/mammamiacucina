# Delivery documents

Pending and Completed Orders, warehouse cards, and order details have two per-order actions:

- **Delivery Print** opens landscape #10 / COM10 stationery inspired by the approved reference. The exact PDF media box is 684 x 297 points (9.5 x 4.125 inches). Choose Actual size / 100% when printing and disable browser headers/footers. The envelope is an explicit user-approved exception to the A4 rule.
- **Delivery Form** opens an A4 delivery document with configured company details, customer identity, customer/profile address, the saved order destination, Toronto delivery appointment, courier/route, order reference/date/status/source, product photos/codes/names, size/packing details, quantities, customer instructions, and receiver name/signature lines. It has no price, total, charge, discount, payment or balance fields. Free-text customer instructions are printed as entered; internal warehouse/payment/audit notes are not included.

Both offer Print and Download PDF. Company details and GST/HST come from General Settings; GST/HST remains at bottom right. HTML and PDFs are private/no-store, use the existing order permissions and warehouse visibility scope, and do not modify orders, invoices, payments, stock, email history or document snapshots. Deleted orders are unavailable. Pickup orders explicitly use their saved pickup address instead of inventing a delivery destination.

The delivery destination and contact data come from the order, so changing the customer's company or alternate address does not redirect an existing order's print. Customer business identity comes from its linked profile. Item names, codes and quantities come from the saved order lines. Older order lines do not store size/packing snapshots: those supplementary details and product photos use the current linked product; missing/deleted products show no guessed size. Existing invoices and their snapshots are untouched.

No migration, package installation, environment change or email-outbox run is needed. Clear/rebuild compiled views when deploying and keep PHP GD enabled for PDF images.

Paper-size reference: https://learn.microsoft.com/en-us/windows/win32/intl/paper-sizes
