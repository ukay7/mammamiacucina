<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GatewaySetting extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return ['credentials' => 'encrypted:array', 'helcim_enabled' => 'boolean', 'stripe_enabled' => 'boolean', 'paypal_enabled' => 'boolean', 'revision' => 'integer'];
    }
}
