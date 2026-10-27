<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerReceiptEvent extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['snapshot' => 'array'];
    }

    public function receipt()
    {
        return $this->belongsTo(CustomerReceipt::class, 'customer_receipt_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
