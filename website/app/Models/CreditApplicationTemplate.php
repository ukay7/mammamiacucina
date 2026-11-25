<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class CreditApplicationTemplate extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'retired_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $template) {
            if ($template->getRawOriginal('published_at') && array_diff(array_keys($template->getDirty()), ['retired_at', 'updated_at'])) {
                throw new LogicException('Published credit application templates are immutable.');
            }
        });
    }

    public function getVersionAttribute(): string
    {
        return 'v'.$this->id;
    }
}
