# Interior page design preview

All requested pages use layouts/mmc-page.blade.php and the shared mmc-header/mmc-footer partials also used by the homepage. Original page templates are retained in .tools/page-preview-backups at the repository root.

Styles: public/assets/css/mmc-pages.css. Product records: config/catalogue.php. Browser-only cart: public/assets/js/mmc-shop.js using sessionStorage (no personal details saved). Checkout/contact are explicitly preview-only. Products without prices link to enquiries.

Review routes: /about, /gallery, /contact, /product-grid, /product-detail?product=cassata-siciliana, /cart, /checkout, /order-success.

Verified: all routes HTTP 200, Blade compilation, JS syntax, desktop detail/contact/gallery, mobile cart/category dropdown, add-to-cart persistence, quantity subtotal, checkout-to-confirmation.
