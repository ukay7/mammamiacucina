<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        $casts = ['is_active' => 'boolean'];
        foreach (config('product_fields') as $key => $field) {
            if ($field[1] === 'decimal') {
                $casts[$key] = 'decimal:8';
            }
        }

        return $casts;
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
