<?php

namespace App\Services;

use App\Models\GatewaySetting;
use Illuminate\Support\Facades\Schema;

class GatewayConfiguration
{
    public const FIELDS = ['stripe' => ['secret' => 'Secret key', 'webhook_secret' => 'Webhook signing secret'], 'paypal' => ['client_id' => 'Client ID', 'secret' => 'Client secret', 'webhook_id' => 'Webhook ID']];

    public function record(): ?GatewaySetting
    {
        return Schema::hasTable('gateway_settings') ? GatewaySetting::find(1) : null;
    }

    public function mode(): string
    {
        return $this->record()?->mode ?? config('payments.mode', 'sandbox');
    }

    public function enabled(string $provider): bool
    {
        return (bool) ($this->record()?->getAttribute($provider.'_enabled') ?? config("payments.$provider.enabled", false));
    }

    public function allCredentials(): array
    {
        $record = $this->record();
        if ($record) {
            return $record->credentials ?? [];
        }
        $values = [];
        $mode = config('payments.mode', 'sandbox');
        foreach (self::FIELDS as $provider => $fields) {
            foreach ($fields as $field => $label) {
                $values[$mode][$provider][$field] = config("payments.$provider.$field");
            }
        }

        return $values;
    }

    public function credentials(string $provider, string $mode): array
    {
        return $this->allCredentials()[$mode][$provider] ?? [];
    }

    public function configured(string $provider, string $mode): bool
    {
        if (! isset(self::FIELDS[$provider]) || ! in_array($mode, ['sandbox', 'live'], true)) {
            return false;
        }
        $values = $this->credentials($provider, $mode);
        foreach (self::FIELDS[$provider] as $key => $label) {
            if (empty($values[$key])) {
                return false;
            }
        }

        return $provider !== 'stripe' || str_starts_with($values['secret'], $mode === 'live' ? 'sk_live_' : 'sk_test_');
    }
}
