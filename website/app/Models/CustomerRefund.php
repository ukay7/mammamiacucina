<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerRefund extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['snapshot'=>'array', 'total_cents'=>'integer', 'tax_cents'=>'integer', 'revision'=>'integer']; }
    public function customer() { return $this->belongsTo(Customer::class); }
    public function order() { return $this->belongsTo(Order::class)->withTrashed(); }
    public function author() { return $this->belongsTo(User::class, 'created_by'); }
    public function lines() { return $this->hasMany(CustomerRefundLine::class)->orderBy('id'); }
    public function events() { return $this->hasMany(CustomerRefundEvent::class)->orderBy('id'); }
    public function scopeSummary($query) { return $query->select(['id','number','customer_id','order_id','total_cents','tax_cents','reason','created_by','revision','created_at','updated_at']); }
}
