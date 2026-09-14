<?php

use Illuminate\Support\Facades\Route;

require __DIR__.'/admin.php';
Route::get('/banner-images/{banner}', [\App\Http\Controllers\Admin\BannerController::class,'image'])->name('banner.image');

foreach (config('theme.pages') as $page) {
    Route::view($page === 'index' ? '/' : '/'.$page, 'pages.'.$page)->name('theme.'.$page);
    Route::redirect('/'.$page.'.html', $page === 'index' ? '/' : '/'.$page, 301);
}
Route::redirect('/homepage.html', '/', 301);
Route::redirect('/search-result.html', '/product-grid', 301);
Route::redirect('/wishlist', '/whist-list', 301);
Route::fallback(fn () => response()->view('pages.404', [], 404));
