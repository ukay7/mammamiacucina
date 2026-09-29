<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AboutPage extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['items' => 'array', 'revision' => 'integer'];
    }

    public function imageUrl(): string
    {
        return $this->image_path ? route('about.image', ['v' => $this->revision]) : asset('assets/images/mmc/category-cakes-hd.png');
    }
}
