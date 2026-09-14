# Mamma Mia Cucina

Laravel website design and interactive frontend preview for Mamma Mia Cucina.

## Project folders

- `website/`: Laravel application, shared header/footer, product catalogue preview, and website assets.
- `theme_vanila/`: Original bakery theme and its documentation.
- `mockups/`: Design references.
- `samplepictures/`: Supplied reference photos.

## Run locally

Requires PHP 8.2 or newer and Composer. Use a supported PHP version rather than an older XAMPP PHP installation.

```sh
cd website
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve --host=127.0.0.1 --port=8088
```

On Windows, use `Copy-Item .env.example .env` instead of `cp` if needed. Laravel's migration command can create the SQLite database when prompted.

Open http://127.0.0.1:8088/. The CSS and JavaScript used by the preview are served directly from `website/public/assets`.

## Preview pages

Home, About Us, Gallery, Contact Us, Products Grid, Product Details, Cart, Checkout, and Order Confirmation. Product category filters, sorting, pagination, and media thumbnails are included.

The cart uses browser session storage. Checkout and contact forms are design previews: no real orders, payments, or messages are submitted. Backend integration is the next phase.

Secrets, local databases, installed dependencies, logs, caches, and local PHP tooling are intentionally excluded. Use `.env.example` to configure a fresh installation.
