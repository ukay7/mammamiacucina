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
        $this->app->resolving('mail.manager', function () {
            app(\App\Services\SmtpConfiguration::class)->apply();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\Queue::before(function () {
            app(\App\Services\SmtpConfiguration::class)->apply();
            app('mail.manager')->forgetMailers();
        });
        View::composer('partials.theme-colors',fn($view)=>$view->with('themeSettings',\App\Models\ThemeSetting::find(1)));
        View::composer(['partials.mmc-tradition','partials.mmc-baking'],function($view){$key=$view->name()==='partials.mmc-tradition'?'tradition':'baking';$view->with('homeSection',\App\Models\HomeSection::findOrFail($key));});
        View::composer(['layouts.mmc-page','partials.mmc-header','partials.mmc-footer','partials.mmc-about-video','partials.mmc-product-media','pages.contact','orders.print','admin.layout','admin.quotations.document'], function ($view) {
            $view->with('siteSettings', \App\Models\GeneralSetting::find(1));
        });
        View::composer('partials.mmc-header', function ($view) {
            $view->with('cart', app(\App\Services\StorefrontCart::class)->snapshot());
            $view->with('menuCategories', Category::where('is_active', true)->where('show_to_customer', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'slug']));
        });
        View::composer('partials.mmc-categories', function ($view) {
            $view->with('homeCategories', Category::where('is_active', true)->where('show_to_customer', true)->orderBy('sort_order')->orderBy('name')->get());
        });
        View::composer('partials.mmc-most-loved', function ($view) {
            $category = Category::where('homepage_key', 'most_loved')->where('is_active', true)->first();
            $view->with('lovedProducts', $category ? $category->products()->where('products.is_active', true)->with(['media' => fn ($q) => $q->coverImage()])->orderBy('products.id')->get() : collect());
        });
        View::composer('partials.mmc-new-arrivals', function ($view) {
            $view->with('arrivalProducts', \App\Models\Product::where('is_active', true)->whereHas('categories', fn ($q) => $q->where('is_active', true))->whereHas('media', fn ($q) => $q->where('kind', 'image'))->with(['media' => fn ($q) => $q->coverImage()])->inRandomOrder()->limit(10)->get());
        });
        View::composer('partials.mmc-hero', function ($view) {
            $view->with('banners', Banner::where('is_active', true)->orderBy('priority')->orderBy('id')->get());
        });
    }
}
