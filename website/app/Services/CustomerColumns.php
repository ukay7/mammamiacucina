<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CustomerColumns
{
    public function labels(): array
    {
        return ['id' => 'Customer ID', 'identity' => 'Business / name / email', 'phone' => 'Phone',
            'account_type' => 'Account type', 'verified' => 'Email verified', 'status' => 'Status',
            'created_at' => 'Created at', 'orders' => 'Orders', 'last_order' => 'Last order'];
    }

    public function selected(Request $request): array
    {
        $request->validate(['columns' => 'nullable|string|max:500']);
        $allowed = array_keys($this->labels());
        if (! $request->has('columns')) {
            return $allowed;
        }
        $keys = array_values(array_unique(array_filter(explode(',', (string) $request->input('columns')))));
        if (array_diff($keys, $allowed)) {
            throw ValidationException::withMessages(['columns' => 'Unknown customer column selected.']);
        }

        return array_values(array_intersect($allowed, $keys));
    }
}
