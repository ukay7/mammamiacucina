# MMC Laravel theme preview

Open http://127.0.0.1:8088. The original page directory is at /pages.

To restart, open PowerShell in this folder and run:

```powershell
powershell -ExecutionPolicy Bypass -File .\start-local.ps1
```

The project uses Laravel 12 and a project-local PHP 8.4 runtime in ../.tools/php. XAMPP's PHP 7.4 is unchanged; use the URL above rather than Apache's /website URL.

All 21 website HTML pages and the original README are Blade views in resources/views/pages. Shared markup lives in resources/views/layouts and resources/views/partials. Assets are in public/assets. config/theme.php lists pages; routes/web.php registers named routes and redirects old .html URLs.

The source theme remains unchanged in ../theme_vanila/bakery. Plugin demos and source resources remain in the original theme; they are not customer website pages.

This phase preserves static content. Cart, checkout, search, subscriptions and contact forms have no backend. Navigation forms use GET; placeholder forms display a preview notice. Google Maps is disabled until a valid project API key is configured. Google Fonts and externally hosted media still need internet access.

