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

    protected static function booted(): void
    {
        static::saved(function(Inventory $inventory){
            if(!\Illuminate\Support\Facades\Schema::hasTable('warehouse_inventories') || (!$inventory->wasRecentlyCreated && !$inventory->wasChanged('quantity_on_hand'))) return;
            $warehouse=Warehouse::where('is_default',true)->first(); if(!$warehouse)return;
            $row=WarehouseInventory::firstOrNew(['warehouse_id'=>$warehouse->id,'product_id'=>$inventory->product_id]);
            $before=$inventory->getOriginal('quantity_on_hand'); $after=$inventory->quantity_on_hand;
            if($after===null){$row->delete();return;}
            $row->quantity_on_hand=$before===null?(float)$after:($row->quantity_on_hand===null?(float)$after-(float)$before:(float)$row->quantity_on_hand+((float)$after-(float)$before)); $row->save();
        });
    }
}
