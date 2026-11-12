<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Support\Facades\Validator;

class CustomerDeliveryAddress
{
    public const FIELDS = ['address', 'city', 'province', 'postal_code', 'country'];

    public function company(Customer $customer): array
    {
        return $this->normalize($customer->only(self::FIELDS));
    }

    public function normalize(array $address): array
    {
        $result = [];
        foreach (self::FIELDS as $field) $result[$field] = trim((string) ($address[$field] ?? ''));
        return $result;
    }

    public function complete(array $address): bool
    {
        return ! in_array('', $this->normalize($address), true);
    }

    public function resolve(Customer $customer, array $input, bool $required = true): array
    {
        // Older checkout tabs have no selector. Treat their address as a separate
        // destination too; checkout must never replace the company/profile address.
        $type = $input['delivery_address_type'] ?? 'alternate';
        Validator::make(['delivery_address_type' => $type], ['delivery_address_type' => 'required|string|in:company,alternate'])->validate();
        $address = $type === 'company' ? $this->company($customer) : array_intersect_key($input, array_flip(self::FIELDS));
        $rules = [];
        foreach (self::FIELDS as $field) $rules[$field] = ($required ? 'required' : 'nullable').'|string|max:'.match ($field) {
            'address' => 255, 'postal_code' => 30, default => 100,
        };
        Validator::make($address, $rules, ['required' => 'The delivery :attribute is required.'], ['address' => 'street address', 'postal_code' => 'postal code'])->validate();
        return $this->normalize($address);
    }

    public function remember(Customer $customer, array $input, array $address): void
    {
        if (($input['delivery_address_type'] ?? 'alternate') === 'alternate') {
            $customer->update(['delivery_address' => $this->normalize($address)]);
        }
    }
}
