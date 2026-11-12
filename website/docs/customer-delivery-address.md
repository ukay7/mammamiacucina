# Delivery address selection

Customer checkout and admin Quick Sale / New Order now ask **Where should we deliver this order?** near the final order button. Business customers can choose **Company address**; individual customers see **Profile address**. **Different delivery address** opens a separate, editable destination, such as a warehouse.

The company/profile address is read from the customer record and remains unchanged during checkout. Edit it through the existing customer profile controls. The separate address is stored in `customers.delivery_address` after a delivery order is successfully saved. It is available to both customer and admin on future orders; the company/profile option remains the default when complete. If the profile address is incomplete, the separate address option is selected instead.

The order's existing address fields capture the selected destination. Updating either saved address later does not update earlier orders. Existing print, PDF, email and warehouse views continue using the order's destination. Pickup bypasses delivery-address requirements and does not save an address.

Postal/service delivery quotes follow the selected destination. Changing the selection invalidates the displayed quote. New POS quotes also bind the full address and selected option, so changed destinations require a fresh review before the sale can be completed. Customer identity, pricing, existing payment controls and retry protection remain in force.

The additive migration `2026_11_12_140000_add_customer_delivery_address.php` adds one nullable JSON column, without changing existing company addresses or orders. Existing tabs that submit an address without the new radio field are treated as a separate destination; they cannot overwrite the company/profile address. No historical addresses are guessed or backfilled.

Validation: `CustomerDeliveryAddressTest` covers both order flows, shared persistence, company-address protection, historical destinations, customer isolation, incomplete/invalid addresses, pickup, retries, stale POS quotes and delivery rates for each destination.
