<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'priority' => 'integer'];
    }

    public function getImageUrlAttribute(): string
    {
        return $this->image_path ? route('banner.image', $this) : asset($this->asset_path);
    }
}
