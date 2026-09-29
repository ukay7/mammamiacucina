<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    public static function usesBusinessPricing():bool {
        $user=auth()->user();
        return $user && $user->isCustomer() && $user->is_active && $user->email_verified_at && $user->account_type==='business';
    }
    public static function storefrontPriceSql():string {
        return static::usesBusinessPricing() ? 'COALESCE(business_selling_price_cad,total_selling_price_cad)' : 'total_selling_price_cad';
    }
    public function getStorefrontPriceAttribute(){
        return static::usesBusinessPricing() ? ($this->business_selling_price_cad ?? $this->total_selling_price_cad) : $this->total_selling_price_cad;
    }
    protected $guarded = ['id'];

    protected function casts(): array
    {
        $casts = ['is_active' => 'boolean', 'dna' => 'array', 'editor_revision' => 'integer'];
        foreach (config('product_fields') as $key => $field) {
            if ($field[1] === 'decimal') {
                $casts[$key] = 'decimal:8';
            }
        }

        return $casts;
    }

    public function getBarcodeNumberAttribute(): string
    {
        return (string) $this->qr_code;
    }

    public function pricingDraft()
    {
        return $this->hasOne(ProductPricingDraft::class);
    }

    public function priceReviews()
    {
        return $this->hasMany(ProductPriceReview::class);
    }

    public function documents()
    {
        return $this->hasMany(ProductDocument::class);
    }

    public function media()
    {
        return $this->hasMany(ProductMedia::class)->orderBy('sort_order')->orderBy('id');
    }

    protected static function booted(): void
    {
        // Keep the existing primary category compatible with imports and older callers.
        static::saved(function (Product $product) {
            if ($product->wasRecentlyCreated || $product->wasChanged('category_id')) {
                $product->categories()->syncWithoutDetaching([$product->category_id]);
            }
        });
    }

    public function allergies()
    {
        return $this->belongsToMany(Allergy::class)->orderBy('name');
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class)->orderBy('name');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function inventory()
    {
        return $this->hasOne(Inventory::class);
    }

    public function movements()
    {
        return $this->hasMany(InventoryMovement::class);
    }
}
