<?php

namespace App\Services;

use App\Models\GeneralSetting;
use App\Models\User;
use Illuminate\Support\Str;

class CheckoutReview
{
    public function prepare(User $user, array $cart, bool $fromCatalogue = false): array
    {
        [$firstName, $lastName] = array_pad(explode(' ', trim($user->name), 2), 2, '');
        $customer = $user->customerRecord();
        $profile = array_merge([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $user->email,
            'phone' => $user->phone,
            'country' => 'Canada',
        ], array_filter($customer->only(['address', 'city', 'province', 'postal_code', 'country']), fn ($value) => $value !== null && $value !== ''));

        $quote = [];
        foreach ($cart['items'] as $item) {
            $quote[$item['product']->id] = [$item['quantity'], $item['unit_cents']];
        }

        $settings = GeneralSetting::findOrFail(1);
        $charges = ['delivery' => $settings->delivery_cents, 'tax' => $settings->taxFor($cart['total']), 'rate' => $settings->tax_basis_points];
        $token = (string) Str::uuid();
        session([
            'checkout_charges' => [
                'delivery' => $settings->delivery_cents,
                'rate' => $settings->tax_basis_points,
                'matrix' => (bool) $settings->matrix_delivery_enabled,
                'pickup_address' => $settings->pickup_address,
            ],
            'checkout_terms_revision' => $customer->commercial_revision,
            'checkout_token' => $token,
            'checkout_quote' => $quote,
            'checkout_company_address' => app(CustomerDeliveryAddress::class)->company($customer),
            'checkout_from_catalogue' => $fromCatalogue,
        ]);

        return compact('cart', 'token', 'charges', 'profile', 'settings', 'customer');
    }
}
