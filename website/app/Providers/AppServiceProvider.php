<?php

namespace App\Providers;

use App\Models\Banner;
use App\Models\Category;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('partials.mmc-header', function ($view) {
            $view->with('cart', app(\App\Services\StorefrontCart::class)->snapshot());
            $view->with('menuCategories', Category::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'slug']));
        });
        View::composer('partials.mmc-hero', function ($view) {
            $view->with('banners', Banner::where('is_active', true)->orderBy('priority')->orderBy('id')->get());
        });
    }
}
