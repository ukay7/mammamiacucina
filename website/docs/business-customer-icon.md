# Business customer icon

General Settings provides one shared icon for business accounts. The bundled default is Bootstrap Icons' Building Fill, coloured gold and rendered to a transparent PNG; its source and MIT licence are in `public/assets/images/business-customer`. It works immediately without an upload or external image host. Upload PNG, JPEG or WebP up to 2 MB and 2048 pixels in either dimension to replace it. PHP GD normalizes uploads to a transparent 128 × 128 PNG. The icon is displayed at 14 × 14 pixels, with an accessible “Business customer” label. Individual and guest customers have no icon. An unset or missing custom file falls back to the bundled default.

Uploads use the existing settings permission and revision check. Replacements remove the old file only after the settings transaction commits; failed/stale saves remove only the new file. The restore-default checkbox clears the custom setting. Existing catalogue-upload limits are independent.

Apply `2026_11_16_120000_add_business_customer_icon.php` when deploying. Files live on the local storage disk under `branding/business-customers`. The public branding endpoint uses `nosniff` and revalidation; no customer data is exposed there.

Use `<x-business-customer-icon :customer="$customer" />` immediately after the principal customer/company name in new Blade screens. SQL result rows should explicitly select `account_type`, including null for guests, rather than infer business status from a company name. For a linked ID use `:customer-id`; for document payloads use an explicit `:business` boolean. Use `:embedded="true"` in PDF templates so no remote image fetch is required. Keep existing shared document styling and financial snapshots intact.

New receipt, refund, quotation and pricing-list customer snapshots include account type. Older snapshots resolve the linked customer read-only; list views batch these lookups. Dynamic selectors use `mmcBusinessCustomerIcon.append` and DOM text nodes, never customer-supplied HTML.

Regression coverage: `BusinessCustomerIconTest` checks upload lifecycle, stale edits, validation/permissions, account classification, HTML escaping, customer/order views, embedded document images and unchanged order data.
