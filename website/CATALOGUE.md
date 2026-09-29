# Catalogue deployment
The additive 2026_09_29_110000_create_catalogue_pages migration creates the catalogue-page table and imports the 36 supplied sample pages. It does not modify products, categories, banners or orders. Matching category names are linked; unmatched sections use catalogue-only labels.

Deploy application files, database/data/catalogue-pages.json, and all files in public/assets/catalogue, along with the reader CSS/JS. Run the normal deployment migration (php artisan migrate --force) and clear/rebuild application caches. Do not run database reset commands.

After deployment, manage pages at /admin/catalogue. Multiple pages can share a product category. A blank product category requires a catalogue-only label. Published pages appear in ascending display order. Deleting a catalogue page never deletes its category or products.

Images uploaded through the admin live on Laravel's local storage disk under catalogue-pages. Preserve that storage directory on deployments and back it up with the database. Local uploads or local edits made after the initial sample import require explicit transfer; Git alone does not synchronize database content or uploaded files.

The original sample HTML under public/sample is a development reference and is not required by the catalogue.

Performance: bundled sample pages and small thumbnails are served directly from public/assets/catalogue, with file-versioned URLs. The supplied folder .htaccess enables one-day static caching on Apache with mod_headers. For Nginx, configure an equivalent static-image Cache-Control header if not already provided by the host. Uploaded page responses use private one-day caching with revision-based URLs and conditional ETags; admin previews retain the admin no-store policy.
