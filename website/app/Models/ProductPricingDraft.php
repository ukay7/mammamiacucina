<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductPricingDraft extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['inputs' => 'array', 'options' => 'array', 'revision' => 'integer'];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
