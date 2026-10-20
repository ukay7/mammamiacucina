<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(function(self $item){if(!$item->inventory_warehouse_id && \Illuminate\Support\Facades\Schema::hasTable('warehouses'))$item->inventory_warehouse_id=Warehouse::where('is_default',true)->value('id');});
    }

    protected function casts(): array
    {
        return ['inventory_shortage_quantity' => 'decimal:3'];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
