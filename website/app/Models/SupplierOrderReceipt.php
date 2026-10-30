<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierOrderReceipt extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['add_to_inventory' => 'boolean', 'initialize_unset' => 'boolean', 'received_at' => 'datetime'];
    }

    public function order()
    {
        return $this->belongsTo(SupplierOrder::class, 'supplier_order_id');
    }

    public function items()
    {
        return $this->hasMany(SupplierOrderReceiptItem::class)->orderBy('id');
    }
}
