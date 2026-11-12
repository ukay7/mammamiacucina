<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PricingListNumber extends Model
{
    protected $guarded=['id'];

    protected function casts(): array
    {
        return ['created_by'=>'integer','pricing_list_id'=>'integer'];
    }

    public function getNumberAttribute(): string
    {
        return 'PL-'.str_pad((string)$this->id,5,'0',STR_PAD_LEFT);
    }
}
