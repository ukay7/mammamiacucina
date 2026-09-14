<?php

use Illuminate\Support\Facades\Route;

foreach (config('theme.pages') as $page) {
    Route::view($page === 'index' ? '/' : '/'.$page, 'pages.'.$page)->name('theme.'.$page);
    Route::redirect('/'.$page.'.html', $page === 'index' ? '/' : '/'.$page, 301);
}
Route::redirect('/homepage.html', '/', 301);
Route::redirect('/search-result.html', '/product-grid', 301);
Route::redirect('/wishlist', '/whist-list', 301);
Route::fallback(fn () => response()->view('pages.404', [], 404));
