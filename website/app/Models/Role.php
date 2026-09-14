<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $fillable = ['name', 'permissions'];

    protected function casts(): array
    {
        return ['permissions' => 'array', 'is_super' => 'boolean'];
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
