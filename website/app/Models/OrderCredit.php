<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderCredit extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['snapshot'=>'array','amount_cents'=>'integer','tax_cents'=>'integer']; }
    public function order() { return $this->belongsTo(Order::class)->withTrashed(); }
    public function customer() { return $this->belongsTo(Customer::class); }
    public function refundLine() { return $this->belongsTo(CustomerRefundLine::class, 'customer_refund_line_id'); }
}
