<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PricingList extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['items'=>'array', 'filters'=>'array', 'customer_snapshot'=>'array', 'brand_snapshot'=>'array',
            'effective_date'=>'date', 'valid_until'=>'date', 'show_images'=>'boolean', 'revision'=>'integer'];
    }

    public function customer() { return $this->belongsTo(Customer::class); }
    public function author() { return $this->belongsTo(User::class, 'created_by'); }
    public function getNumberAttribute(?string $value): string
    {
        return $value ?? ($this->id ? 'PL-'.str_pad((string)$this->id,5,'0',STR_PAD_LEFT) : '');
    }
}
