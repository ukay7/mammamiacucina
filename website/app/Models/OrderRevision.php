<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderRevision extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array
    {
        return ['before'=>'array','after'=>'array','stock_returns'=>'array','brand'=>'array',
            'revision'=>'integer','difference_cents'=>'integer','tax_difference_cents'=>'integer'];
    }
    public function order() { return $this->belongsTo(Order::class)->withTrashed(); }
    public function author() { return $this->belongsTo(User::class, 'created_by'); }
}
