<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierOrderItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['pricing_snapshot' => 'array', 'quantity' => 'decimal:3', 'unit_cost' => 'decimal:8', 'line_total' => 'decimal:2'];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
