<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierOrderReceiptItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'quantity_before' => 'decimal:3', 'quantity_after' => 'decimal:3', 'total_before' => 'decimal:3', 'total_after' => 'decimal:3'];
    }
}
