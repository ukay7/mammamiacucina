<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryMovement extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(function(self $movement){if(!$movement->warehouse_id && \Illuminate\Support\Facades\Schema::hasTable('warehouses'))$movement->warehouse_id=Warehouse::where('is_default',true)->value('id');});
    }

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function warehouse(){return $this->belongsTo(Warehouse::class);}
}
