<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderSettlement extends Model
{
    protected $guarded = ['id'];

    public function author()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'related_original' => 'boolean'];
    }

    public function getKindAttribute(): string
    {
        return $this->entry_kind ?: ($this->amount_cents < 0 ? 'refund' : 'payment');
    }

    public function getEntryLabelAttribute(): string
    {
        return match ($this->kind) {
            'reversal' => 'Payment reversal',
            'bounced_cheque' => 'Cheque bounced — payment reversed',
            'correction_debit' => 'Method correction — removed',
            'correction_credit' => 'Method correction — reassigned',
            'refund' => 'Refund',
            default => 'Payment received',
        };
    }
}
