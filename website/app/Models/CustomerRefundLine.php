<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerRefundLine extends Model
{
    public const METHODS = CustomerReceiptLine::METHODS;
    public const STATUSES = ['confirmed'=>'Refund confirmed', 'awaiting_clearance'=>'Cheque awaiting clearance', 'cleared'=>'Cheque cleared', 'cancelled'=>'Cheque cancelled'];
    protected $guarded = ['id'];
    protected function casts(): array { return ['amount_cents'=>'integer','tax_cents'=>'integer','refund_date'=>'date','cheque_date'=>'date','resolved_on'=>'date']; }
    public function refund() { return $this->belongsTo(CustomerRefund::class, 'customer_refund_id'); }
    public function credit() { return $this->hasOne(OrderCredit::class); }
    public function settlement() { return $this->hasOne(OrderSettlement::class); }
    public function getStatusLabelAttribute(): string { return self::STATUSES[$this->status] ?? $this->status; }
}
