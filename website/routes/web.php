<?php

use App\Http\Controllers\AboutPageController;
use App\Http\Controllers\Admin\AllergyController;
use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\GeneralSettingController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CatalogueController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\OrderTrackingController;
use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/customer.php';
require __DIR__.'/admin.php';
Route::get('/catalogue', [\App\Http\Controllers\CatalogueBookController::class,'show'])->name('theme.catalogue');
Route::get('/catalogue/reader', [\App\Http\Controllers\CatalogueBookController::class,'reader'])->name('catalogue.reader');
Route::get('/catalogue/pages/{page}/image', [\App\Http\Controllers\CatalogueBookController::class,'image'])->name('catalogue.image');
Route::get('/contact-image', [\App\Http\Controllers\ContactPageController::class,'image'])->name('contact.image');
Route::get('/about-image', [AboutPageController::class, 'image'])->name('about.image');
Route::post('/payments/webhooks/{provider}', [PaymentController::class, 'webhook'])->whereIn('provider', ['stripe', 'paypal'])->name('payment.webhook');
Route::get('/payments/{payment:reference}', [PaymentController::class, 'show'])->name('payment.show');
Route::get('/payments/{payment:reference}/return', [PaymentController::class, 'returned'])->name('payment.return');
Route::get('/payments/{payment:reference}/cancel-return', [PaymentController::class, 'cancelled'])->name('payment.cancel-return');
foreach (['start', 'check', 'cancel'] as $action) {
    Route::post('/payments/{payment:reference}/'.$action, [PaymentController::class, $action])->middleware('throttle:15,1')->block(10, 10)->name('payment.'.$action);
}
Route::get('/allergy-icons/{allergy}', [AllergyController::class, 'icon'])->name('allergy.icon');
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:5,1,contact-form')->name('contact.store');
Route::get('/gallery/events/{event}', [GalleryController::class, 'show'])->name('gallery.event');
Route::get('/gallery/photos/{photo}', [GalleryController::class, 'image'])->name('gallery.image');
Route::get('/site-logo', [GeneralSettingController::class, 'logo'])->name('site.logo');
Route::get('/track-order', [OrderTrackingController::class, 'form'])->name('order.track-form');
Route::post('/track-order', [OrderTrackingController::class, 'lookup'])->middleware('throttle:6,1,order-lookup')->name('order.lookup');
Route::post('/track-order/{token}/verify', [OrderTrackingController::class, 'verify'])->middleware('throttle:6,1,order-email-verify')->name('order.verify');
Route::get('/track-order/{token}/print', [OrderTrackingController::class, 'printOrder'])->middleware('throttle:60,1,order-tracking')->name('order.track-print');
Route::get('/track-order/{token}', [OrderTrackingController::class, 'show'])->middleware('throttle:60,1,order-tracking')->name('order.track');
Route::get('/order-print', [CheckoutController::class, 'printOrder'])->name('order.print');
Route::get('/category-images/{category}', [CategoryController::class, 'image'])->name('category.image');
Route::get('/banner-images/{banner}', [BannerController::class, 'image'])->name('banner.image');

Route::get('/products/{slug}', [CatalogueController::class, 'show'])->name('catalogue.product');
Route::get('/product-media/{product}/{media}', [CatalogueController::class, 'media'])->name('catalogue.media');
Route::post('/cart/items/{product}', [CartController::class, 'save'])->block(10, 10)->name('cart.add');
Route::patch('/cart/items/{product}', [CartController::class, 'save'])->block(10, 10)->name('cart.update');
Route::delete('/cart/items/{product}', [CartController::class, 'remove'])->block(10, 10)->name('cart.remove');
Route::post('/checkout', [CheckoutController::class, 'store'])->block(10, 10)->middleware('throttle:20,1')->middleware(\App\Http\Middleware\CustomerAccess::class)->name('checkout.store');
foreach (config('theme.pages') as $page) {
    if ($page === 'about') {
        Route::get('/about', [AboutPageController::class, 'show'])->name('theme.about');
        Route::redirect('/about.html', '/about', 301);

        continue;
    }
    if ($page === 'product-detail') {
        Route::redirect('/product-detail', '/product-grid', 301)->name('theme.product-detail');
        Route::redirect('/product-detail.html', '/product-grid', 301);

        continue;
    }
    if ($page === 'gallery') {
        Route::get('/gallery', [GalleryController::class, 'index'])->name('theme.gallery');
        Route::redirect('/gallery.html', '/gallery', 301);

        continue;
    }
    if (in_array($page, ['checkout', 'order-success'])) {
        Route::get('/'.$page, [CheckoutController::class, $page === 'checkout' ? 'create' : 'success'])->middleware($page === 'checkout' ? [\App\Http\Middleware\CustomerAccess::class] : [])->name('theme.'.$page);
        Route::redirect('/'.$page.'.html', '/'.$page, 301);

        continue;
    }
    if ($page === 'cart') {
        Route::get('/cart', [CartController::class, 'index'])->name('theme.cart');
        Route::redirect('/cart.html', '/cart', 301);

        continue;
    }
    if ($page === 'product-grid') {
        Route::get('/product-grid', [CatalogueController::class, 'index'])->name('theme.product-grid');
        Route::redirect('/product-grid.html', '/product-grid', 301);

        continue;
    }
    Route::view($page === 'index' ? '/' : '/'.$page, 'pages.'.$page)->name('theme.'.$page);
    Route::redirect('/'.$page.'.html', $page === 'index' ? '/' : '/'.$page, 301);
}
Route::redirect('/homepage.html', '/', 301);
Route::redirect('/search-result.html', '/product-grid', 301);
Route::redirect('/wishlist', '/whist-list', 301);
Route::fallback(fn () => response()->view('pages.404', [], 404));

Route::post('/checkout/delivery-quote',\App\Http\Controllers\DeliveryQuoteController::class)->middleware([\App\Http\Middleware\CustomerAccess::class,'throttle:60,1'])->name('checkout.delivery-quote');

Route::post('/checkout/delivery-options',[\App\Http\Controllers\DeliveryQuoteController::class,'options'])->middleware([\App\Http\Middleware\CustomerAccess::class,'throttle:60,1'])->name('checkout.delivery-options');
