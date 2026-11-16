<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PosDraft extends Model
{
    use SoftDeletes;
    protected $guarded = ['id'];
    protected function casts(): array { return ['snapshot'=>'array','revision'=>'integer']; }
    public function customer() { return $this->belongsTo(Customer::class); }
    public function author() { return $this->belongsTo(User::class, 'updated_by'); }
    public function order() { return $this->belongsTo(Order::class)->withTrashed(); }
    public function getNumberAttribute(): string { return 'DRAFT-'.str_pad((string)$this->id, 5, '0', STR_PAD_LEFT); }
}
