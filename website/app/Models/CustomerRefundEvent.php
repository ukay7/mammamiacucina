<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerRefundEvent extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];
    protected function casts(): array { return ['snapshot'=>'array','created_at'=>'datetime']; }
    public function refund() { return $this->belongsTo(CustomerRefund::class, 'customer_refund_id'); }
    public function author() { return $this->belongsTo(User::class, 'user_id'); }
}
