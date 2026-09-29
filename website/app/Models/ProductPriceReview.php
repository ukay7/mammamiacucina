<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductPriceReview extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'draft_revision' => 'integer', 'proposed_cents' => 'integer', 'previous_price' => 'decimal:8', 'reviewed_at' => 'datetime'];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
