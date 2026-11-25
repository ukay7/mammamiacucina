<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerProfileRequest extends Model
{
    public const STATUSES = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['original' => 'array', 'proposed' => 'array', 'reviewed_at' => 'datetime'];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
