<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerReceipt extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['customer_snapshot' => 'array', 'total_cents' => 'integer', 'target_order_id' => 'integer'];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function targetOrder()
    {
        return $this->belongsTo(Order::class, 'target_order_id')->withTrashed();
    }

    public function lines()
    {
        return $this->hasMany(CustomerReceiptLine::class)->orderBy('id');
    }

    public function events()
    {
        return $this->hasMany(CustomerReceiptEvent::class)->orderBy('id');
    }
}
