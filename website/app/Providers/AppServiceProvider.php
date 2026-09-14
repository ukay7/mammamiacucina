<?php

namespace App\Providers;

use App\Models\Banner;
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
        View::composer('partials.mmc-hero', function ($view) {
            $view->with('banners', Banner::where('is_active', true)->orderBy('priority')->orderBy('id')->get());
        });
    }
}
