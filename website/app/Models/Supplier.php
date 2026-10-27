<?php

namespace App\Models;

use App\Services\SupplierDirectory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Supplier extends Model
{
    protected $guarded = ['id', 'name_key'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (Supplier $supplier) {
            $supplier->name = SupplierDirectory::name($supplier->name);
            $supplier->name_key = SupplierDirectory::key($supplier->name);
        });
        static::updated(function (Supplier $supplier) {
            if ($supplier->wasChanged('name')) {
                // Keep legacy display/search/export fields compatible, and invalidate stale editors/import previews.
                $supplier->products()->update(['supplier' => $supplier->name,
                    'editor_revision' => DB::raw('editor_revision + 1')]);
            }
        });
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function orders()
    {
        return $this->hasMany(SupplierOrder::class);
    }

    public function snapshot(): array
    {
        return $this->only(['id', 'name', 'contact_name', 'email', 'phone', 'address']);
    }
}
