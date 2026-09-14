<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductImportRow extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['original_data' => 'array', 'normalized_data' => 'array', 'errors' => 'array'];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
