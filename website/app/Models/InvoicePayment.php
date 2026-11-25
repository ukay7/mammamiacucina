<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoicePayment extends Model
{
    public const ACTIVE = ['initializing', 'pending', 'needs_review'];

    public const STATUSES = [
        'initializing' => 'Preparing', 'pending' => 'Awaiting payment', 'needs_review' => 'Needs review',
        'paid' => 'Paid', 'refunded' => 'Refunded before allocation', 'expired' => 'Expired', 'init_failed' => 'Setup failed',
    ];

    protected $guarded = ['id'];
    protected $hidden = ['checkout_token'];

    protected function casts(): array
    {
        return ['checkout_token' => 'encrypted', 'amount_cents' => 'integer', 'processor_amount_cents' => 'integer',
            'paid_at' => 'datetime', 'last_checked_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function receipt()
    {
        return $this->belongsTo(CustomerReceipt::class, 'customer_receipt_id');
    }

    public function allocations()
    {
        return $this->hasMany(InvoicePaymentAllocation::class)->orderBy('order_id');
    }

    public function events()
    {
        return $this->hasMany(InvoicePaymentEvent::class)->orderBy('id');
    }
}
