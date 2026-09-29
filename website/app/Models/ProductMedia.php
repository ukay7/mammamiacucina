<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductMedia extends Model
{
    protected $table = 'product_media';

    protected $guarded = ['id'];

    public function scopeCoverImage($query)
    {
        // One deterministic image per product, compatible with MySQL/MariaDB and SQLite.
        return $query->where('product_media.id', function ($subquery) {
            $subquery->select('cover.id')->from('product_media as cover')
                ->whereColumn('cover.product_id', 'product_media.product_id')
                ->where('cover.kind', 'image')->orderBy('cover.sort_order')->orderBy('cover.id')->limit(1);
        });
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
