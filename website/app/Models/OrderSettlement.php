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
        return ['amount_cents' => 'integer'];
    }
}
