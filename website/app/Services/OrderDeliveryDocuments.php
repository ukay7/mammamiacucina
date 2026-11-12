<?php

namespace App\Services;

use App\Models\Order;
use App\Support\BusinessTime;
use App\Support\NumberDisplay;

class OrderDeliveryDocuments
{
    // COM10: 9.5 x 4.125 inches, in PDF points (landscape).
    public const ENVELOPE_PAPER = [0, 0, 684, 297];

    public function data(Order $order, bool $envelope = false): array
    {
        $order->loadMissing($envelope ? ['customer'] : ['customer', 'items.product']);
        $customer = $order->customer;
        $contact = trim($order->first_name.' '.$order->last_name);
        $business = $customer?->account_type === 'business' ? trim((string) $customer->business_name) : '';
        $identity = array_values(array_filter([$business ?: $contact,
            $business && $contact && $contact !== $business ? 'Attention: '.$contact : null,
            $customer ? 'Customer account #'.$customer->id : null]));
        $address = fn ($source) => array_values(array_filter([$source->address,
            implode(', ', array_filter([$source->city, $source->province, $source->postal_code])), $source->country]));
        $destination = $order->fulfillment === 'pickup'
            ? array_values(array_filter([$order->pickup_address])) : $address($order);
        $route = [];
        if ($order->fulfillment !== 'pickup') {
            $service = $order->delivery_service_name ?: ($order->delivery_service ? ucwords(str_replace('_', ' ', $order->delivery_service)) : null);
            if ($service) $route['Courier'] = $service;
            foreach (['from' => 'From', 'to' => 'To'] as $direction => $label) {
                $postal = $order->{'delivery_'.$direction.'_postal'};
                $zone = $order->{'delivery_'.$direction.'_zone'};
                if ($postal || $zone !== null) $route[$label] = trim(($postal ?? '').($zone !== null ? ' (Zone '.$zone.')' : ''));
            }
        }
        $images = $envelope ? [] : app(DocumentProductImages::class)->forItems($order->items->toArray());
        // Build a nonfinancial payload directly. Never pass financial summaries,
        // prices, saved route charges, payments or invoice totals to these views.
        return [
            'brand' => app(DocumentTheme::class)->brand(), 'title' => $envelope ? 'Delivery Print' : 'Delivery Form',
            'number' => $order->number, 'date' => BusinessTime::format($order->ordered_at ?? $order->created_at),
            'identity' => $identity, 'contact' => array_values(array_filter([$order->email, $order->phone])),
            'billing_address' => $customer ? $address($customer) : $address($order),
            'destination' => $destination, 'destination_label' => $order->fulfillment === 'pickup' ? 'Pick up at' : 'Deliver to',
            'fulfillment' => $order->fulfillment === 'pickup' ? 'Pick up' : 'Delivery',
            'schedule' => $order->fulfillment === 'delivery' && $order->delivery_scheduled_at
                ? BusinessTime::format($order->delivery_scheduled_at, 'D, d M Y, g:i A T').' (Toronto)' : null,
            'status' => $order->status_label, 'source' => $order->source === 'pos' ? 'Quick Sale / POS' : 'Website',
            'route' => $route, 'notes' => $order->notes,
            'items' => $envelope ? [] : $order->items->values()->map(function ($item, $index) use ($images) {
                $product = $item->product;
                $size = [];
                if (filled($product?->size_diameter_cm)) $size[] = $product->size_diameter_cm.' cm';
                foreach (['unit_weight_g' => 'g / piece', 'pack_weight_kg' => 'kg / pack', 'pieces_per_pack' => 'pieces / pack'] as $field => $unit) {
                    if ($product?->$field !== null && (float) $product->$field > 0) $size[] = NumberDisplay::quantity($product->$field).' '.$unit;
                }
                if (filled($product?->uom)) $size[] = 'Unit: '.$product->uom;
                return ['name' => $item->name, 'code' => $item->qr_code ?: $item->product_code,
                    'image' => $images[$index] ?? null, 'size' => $size, 'quantity' => NumberDisplay::quantity($item->quantity)];
            })->all(),
            'italy_art' => $envelope ? 'data:image/svg+xml;base64,'.base64_encode(file_get_contents(public_path('assets/images/delivery-italy.svg'))) : null,
        ];
    }
}
