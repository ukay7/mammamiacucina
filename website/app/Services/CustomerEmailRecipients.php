<?php

namespace App\Services;

use App\Models\Customer;

class CustomerEmailRecipients
{
    public function alternate(string $type, string $primary, ?Customer $customer = null): ?string
    {
        // Invitations and business approval can contain account-verification links too.
        if (preg_match('/verification|invitation|password|business_approved/', $type)
            || in_array($type, ['smtp_test', 'new_order_notification'], true)
            || in_array(config('email_templates.'.$type.'.required'), ['verification_url', 'reset_url'], true)) {
            return null;
        }

        if (! $customer) {
            // Only match the original primary address, never another customer's alternate.
            $matches = Customer::whereRaw('LOWER(email) = ?', [strtolower(trim($primary))])->limit(2)->get();
            if ($matches->count() !== 1) {
                return null;
            }
            $customer = $matches->first();
        }

        $alternate = strtolower(trim((string) $customer->alternate_email));

        return filter_var($alternate, FILTER_VALIDATE_EMAIL) && strcasecmp(trim($primary), $alternate) !== 0
            ? $alternate : null;
    }
}
