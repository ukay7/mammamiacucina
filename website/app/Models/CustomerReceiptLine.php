<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerReceiptLine extends Model
{
    public const METHODS = ['cash' => 'Cash', 'cheque' => 'Cheque', 'etransfer' => 'E-transfer', 'card' => 'Card (external terminal)', 'paypal' => 'PayPal (externally verified)'];

    public const STATUSES = ['confirmed' => 'Confirmed received', 'awaiting_clearance' => 'Awaiting clearance', 'cleared' => 'Cleared', 'reversed' => 'Reversed', 'bounced' => 'Bounced', 'cancelled' => 'Cancelled'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'payment_date' => 'date', 'cheque_date' => 'date', 'cleared_on' => 'date', 'resolved_at' => 'datetime', 'planned_allocations' => 'array'];
    }

    public function receipt()
    {
        return $this->belongsTo(CustomerReceipt::class, 'customer_receipt_id');
    }

    public function allocations()
    {
        return $this->hasMany(OrderSettlement::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
