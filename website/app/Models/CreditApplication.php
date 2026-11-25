<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class CreditApplication extends Model
{
    public const STATUSES = [
        'awaiting_signature' => 'Awaiting signature', 'submitted' => 'Pending review',
        'approved' => 'Approved', 'rejected' => 'Rejected', 'superseded' => 'Superseded',
    ];

    protected $guarded = ['id'];

    protected $hidden = ['draft_details', 'signed_snapshot', 'signed_pdf', 'review_token'];

    protected function casts(): array
    {
        return [
            'draft_details' => 'encrypted:array', 'signed_snapshot' => 'encrypted:array', 'signed_pdf' => 'encrypted',
            'review_note' => 'encrypted', 'prepared_at' => 'datetime', 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $application) {
            if ($application->getRawOriginal('submitted_at') && $application->isDirty([
                'customer_id', 'template_id', 'requested_by', 'draft_details', 'review_token', 'prepared_at',
                'submitted_at', 'signed_snapshot', 'signed_pdf', 'pdf_sha256',
            ])) {
                throw new LogicException('Signed credit application documents are immutable.');
            }
        });
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function template()
    {
        return $this->belongsTo(CreditApplicationTemplate::class);
    }

    public function invitationEmail()
    {
        return $this->belongsTo(EmailHistory::class, 'invitation_email_id');
    }

    public function getReferenceAttribute(): string
    {
        return 'CA-'.str_pad($this->id, 5, '0', STR_PAD_LEFT);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status];
    }
}
