<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockCountItemLog extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'quantity_change' => 'decimal:3',
            'quantity_before' => 'decimal:3',
            'quantity_after' => 'decimal:3',
            'created_at' => 'datetime',
        ];
    }

    public function item()
    {
        return $this->belongsTo(StockCountItem::class, 'stock_count_item_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
