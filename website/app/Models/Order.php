<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Order extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(function ($order) {
            $order->tracking_token ??= Str::random(48);
        });
    }

    public const STATUSES = ['placed' => 'Placed', 'confirmed' => 'Confirmed', 'preparing' => 'Preparing', 'out_for_delivery' => 'Out for Delivery', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'];

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getFinalTotalCentsAttribute(): ?int
    {
        return $this->delivery_cents === null || $this->tax_cents === null ? null : $this->subtotal_cents + $this->delivery_cents + $this->tax_cents;
    }

    public function events()
    {
        return $this->hasMany(OrderEvent::class)->orderBy('id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
}
