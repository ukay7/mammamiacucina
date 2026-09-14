<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['quantity_on_hand' => 'decimal:3', 'low_stock_threshold' => 'decimal:3'];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
