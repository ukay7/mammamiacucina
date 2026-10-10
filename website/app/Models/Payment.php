<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['checkout_token'];

    protected function casts(): array
    {
        return ['checkout_token' => 'encrypted', 'cancel_requested_at' => 'datetime', 'expires_at' => 'datetime', 'paid_at' => 'datetime', 'released_at' => 'datetime', 'last_checked_at' => 'datetime',
            'amount_cents' => 'integer', 'refunded_cents' => 'integer'];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
