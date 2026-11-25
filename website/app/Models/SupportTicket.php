<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportTicket extends Model
{
    public const STATUSES = ['pending' => 'Pending', 'in_progress' => 'In progress', 'resolved' => 'Resolved'];

    protected $guarded = ['id'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function messages()
    {
        return $this->hasMany(SupportMessage::class);
    }

    public function getLabelAttribute(): string
    {
        return 'SUP-'.str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }
}
