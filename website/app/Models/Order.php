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

    public const STATUSES = ['awaiting_payment' => 'Awaiting payment', 'payment_review' => 'Payment needs review', 'placed' => 'Placed', 'confirmed' => 'Confirmed', 'preparing' => 'Preparing', 'out_for_delivery' => 'Out for Delivery', 'delivered' => 'Delivered', 'completed' => 'Completed', 'warehouse_pending' => 'Sent to warehouse', 'packing' => 'Packing', 'warehouse_issue' => 'Warehouse issue — admin action needed', 'ready_to_dispatch' => 'Ready for dispatch', 'cancelled' => 'Cancelled'];

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getFinalTotalCentsAttribute(): ?int
    {
        return $this->delivery_cents === null || $this->tax_cents === null ? null : $this->subtotal_cents + $this->delivery_cents + $this->tax_cents;
    }

    public function settlements()
    {
        return $this->hasMany(OrderSettlement::class);
    }

    public function getCollectedCentsAttribute(): int
    {
        if ($this->payment) {
            return $this->payment->paid_at ? (int) $this->payment->amount_cents : 0;
        }

        return $this->manual_collected_cents !== null ? (int) $this->manual_collected_cents : (in_array($this->payment_status, ['paid', 'refunded'], true) ? ($this->final_total_cents ?? 0) : 0);
    }

    public function getNetReceivedCentsAttribute(): int
    {
        $refund = $this->payment ? (int) $this->payment->refunded_cents : ($this->manual_refunded_cents ?? ($this->payment_status === 'refunded' ? $this->collected_cents : 0));

        return $this->collected_cents - $refund + (int) $this->settlements->sum('amount_cents');
    }

    public function getBalanceCentsAttribute(): int
    {
        if ($this->status === 'cancelled') {
            return -$this->net_received_cents;
        }
        if ($this->payment_status === 'refunded' && $this->net_received_cents === 0) {
            return 0;
        }

        return ($this->final_total_cents ?? 0) - $this->net_received_cents;
    }

    public function getStatusOptionsAttribute(): array
    {
        $choices = match ($this->status) {
            'placed' => ['confirmed', 'warehouse_pending'],
            'confirmed' => ['preparing', 'warehouse_pending'],
            'preparing' => ['out_for_delivery', 'warehouse_pending'],
            'warehouse_pending' => ['warehouse_issue'],
            'packing' => ['warehouse_issue'],
            'warehouse_issue' => ['warehouse_pending'],
            'ready_to_dispatch' => ['out_for_delivery', 'delivered', 'warehouse_pending'],
            'out_for_delivery' => ['delivered'],
            'delivered' => ['completed'],
            default => [],
        };
        if (! in_array($this->status, ['completed', 'delivered', 'cancelled', 'out_for_delivery', 'awaiting_payment', 'payment_review']) && ! $this->payment && $this->payment_status === 'unpaid') {
            $choices[] = 'cancelled';
        }

        return [$this->status => $this->status_label] + array_intersect_key(self::STATUSES, array_flip($choices));
    }

    public function scopeVisibleToWarehouse($query, int $userId)
    {
        return $query->where(fn ($visible) => $visible
            ->where(fn ($own) => $own->where('created_by', $userId)->where('status','completed'))
            ->orWhere(fn ($parked) => $parked->where('source', 'website')
                ->whereIn('status', ['warehouse_pending', 'packing', 'ready_to_dispatch'])));
    }

    public function customer(){return $this->belongsTo(Customer::class);}
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
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
