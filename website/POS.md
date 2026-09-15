# Quick Sale / POS

## Staff workflow

1. Open Admin > Quick Sale / POS. Super Admin has access. For other staff, enable
   "Use Quick Sale, scan products and record payments" in User Types & Permissions.
2. Focus the QR / barcode field and scan with a connected scanner (Enter suffix),
   or choose Scan with camera. The camera supports QR and common barcodes.
   Browser camera access requires HTTPS or localhost and camera permission.
3. Each scan adds one product. Repeated camera frames are ignored until the code
   has been out of view for about two seconds. You can also search by name/code.
4. Adjust quantities with +/-, type the quantity, or Remove. The cart does not
   change stock until the sale is completed.
5. Choose Collected in store or Delivery and select Review & Checkout.
6. Review product prices, tax and delivery charges. Enter customer details
   (optional for walk-ins; name, phone and full address required for delivery).
7. Record Cash or Card after payment. Card means a payment taken separately
   through the store's terminal; this application does not charge cards.
8. Complete sale, print the branded receipt / save PDF, then Start next sale.

## Orders and inventory

- Pricing uses Total Selling Price (CAD), rounded to cents, then multiplied by quantity.
- Tax is calculated on the product subtotal using General Settings.
- Counter collection has zero delivery charge. Delivery uses General Settings.
- Prices, availability and stock are checked again at completion. If changed,
  staff must refresh the review rather than accepting an unseen new total.
- A counter sale requires payment received and is saved as delivered (collected).
  Delivery sales start as placed and can be paid or unpaid.
- POS orders appear in Orders with the POS source badge and staff history.
- Tracked stock is deducted once. "Not set" stock stays untracked, as in online checkout.
- Eligible delivery cancellations use the existing restocking/refund rules.
  Completed counter sales use the existing completed-order rules; this is not
  a new returns/refunds module.
- On an uncertain network response, Retry confirmation reuses the same signed sale
  reference and exact payload. Do not start a replacement sale for that customer.
- An unfinished cart is kept in the current page only; navigating away loses it.

## Barcode labels

- Manage Products displays a stable barcode such as MMC-P-00001234.
- This internal Code128 identifier is derived from the product ID. Existing QR
  codes, supplier product codes and product records are not rewritten.
- POS also accepts existing QR codes and supplier codes. If a code matches multiple
  products, staff must choose the correct product.
- Use Print barcode / PDF for a product, or select products and Print Selected
  Barcodes. Choose copies, then print or Save as PDF in the browser.
- The sheet uses A4 at 100% scale, browser headers/footers off. Trial-print one
  label and scan it on the store's physical equipment before printing a batch.
- There is no automatic carton-to-piece conversion: one scanned unit matches the
  product's configured selling/inventory unit.

## Deployment

The additive migration adds POS source, fulfillment and staff attribution to orders.
It does not delete or seed products, categories, images, banners or inventory.
Use the existing deploy-update.sh workflow after pushing the release; no content
seeder, migrate:fresh or database reset is needed.

## Verification

PosSaleTest covers stock deduction, cancellation restoration, duplicate retries,
price/tax changes, expiry, permissions, barcode lookup, labels and payment methods.
Browser checks use mocked sales and verify desktop/mobile layout, camera duplicate
protection, network retry payloads, generated barcode decoding and printable PDF.
A physical camera, scanner and printer still need a store-device check.
