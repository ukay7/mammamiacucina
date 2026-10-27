<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierOrder extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['supplier_snapshot' => 'array', 'totals' => 'array', 'completed_at' => 'datetime', 'deleted_at' => 'datetime'];
    }

    public function supplierRecord()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items()
    {
        return $this->hasMany(SupplierOrderItem::class)->orderBy('id');
    }

    public function events()
    {
        return $this->hasMany(SupplierOrderEvent::class)->orderBy('id');
    }
}
