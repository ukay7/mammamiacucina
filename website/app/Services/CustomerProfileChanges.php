<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerProfileRequest;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerProfileChanges
{
    public const LABELS = [
        'name' => 'Full name', 'phone' => 'Phone number', 'alternate_email' => 'Alternate email',
        'address' => 'Street address', 'city' => 'City', 'province' => 'Province / State',
        'postal_code' => 'Postal code', 'country' => 'Country', 'business_bin' => 'BIN number',
        'business_name' => 'Business name', 'business_phone' => 'Business contact number',
        'business_email' => 'Business email',
    ];

    private const USER_FIELDS = ['name', 'phone'];

    public function submit(User $user, Customer $customer, array $input): void
    {
        DB::transaction(function () use ($user, $customer, $input) {
            $user = User::lockForUpdate()->findOrFail($user->id);
            $customer = Customer::lockForUpdate()->findOrFail($customer->id);
            if ($customer->profileRequests()->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['profile' => 'Your previous profile request is awaiting review.']);
            }

            $fields = Arr::only($input, array_keys(self::LABELS));
            $current = $this->values($user, $customer, array_keys($fields));
            $proposed = [];
            foreach ($fields as $field => $value) {
                $value = $this->normalize($field, $value);
                if ($current[$field] !== $value) {
                    $proposed[$field] = $value;
                }
            }
            if (! $proposed) {
                throw ValidationException::withMessages(['profile' => 'Change at least one profile field before submitting a request.']);
            }

            $customer->profileRequests()->create([
                'original' => Arr::only($current, array_keys($proposed)),
                'proposed' => $proposed,
            ]);
        }, 3);
    }

    public function review(CustomerProfileRequest $change, User $actor, array $decision): void
    {
        DB::transaction(function () use ($change, $actor, $decision) {
            $user = User::lockForUpdate()->findOrFail($change->customer->user_id);
            $customer = Customer::lockForUpdate()->findOrFail($change->customer_id);
            $change = CustomerProfileRequest::lockForUpdate()->findOrFail($change->id);
            if ($change->status !== 'pending') {
                throw ValidationException::withMessages(['decision' => 'This request has already been reviewed.']);
            }

            if ($decision['status'] === 'approved') {
                $proposed = $change->proposed;
                if (! $user->isCustomer() || $customer->user_id !== $user->id
                    || ($user->account_type !== 'business' && Arr::hasAny($proposed, BusinessDetails::FIELDS))
                    || $this->values($user, $customer, array_keys($proposed)) !== $change->original) {
                    throw ValidationException::withMessages(['decision' => 'The account changed after this request was submitted. Reject it with a note and ask the customer to submit a new request.']);
                }

                $userFields = Arr::only($proposed, self::USER_FIELDS);
                if ($userFields) {
                    $user->forceFill($userFields)->save();
                }
                $customer->update(Arr::except($proposed, self::USER_FIELDS));
            }

            $change->update([
                'status' => $decision['status'],
                'review_note' => $decision['review_note'] ?? null,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
            ]);
        }, 3);
    }

    private function values(User $user, Customer $customer, array $fields): array
    {
        $values = [];
        foreach ($fields as $field) {
            $values[$field] = $this->normalize($field, in_array($field, self::USER_FIELDS, true) ? $user->$field : $customer->$field);
        }

        return $values;
    }

    private function normalize(string $field, ?string $value): ?string
    {
        $value = trim($value ?? '');

        return $value === '' ? null : ($field === 'alternate_email' ? strtolower($value) : $value);
    }
}
