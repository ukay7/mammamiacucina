<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GatewaySetting;
use App\Services\GatewayConfiguration;
use App\Services\PaymentGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GatewaySettingController extends Controller
{
    public function edit(GatewayConfiguration $config)
    {
        $record = $config->record();
        $saved = [];
        foreach (['sandbox', 'live'] as $mode) {
            foreach (GatewayConfiguration::FIELDS as $provider => $fields) {
                foreach ($fields as $key => $label) {
                    $saved[$mode][$provider][$key] = ! empty($config->credentials($provider, $mode)[$key]);
                }
            }
        }

        return response()->view('admin.settings.gateways', ['mode' => $config->mode(), 'revision' => $record?->revision ?? 0,
            'enabled' => ['helcim' => $config->enabled('helcim'), 'stripe' => $config->enabled('stripe'), 'paypal' => $config->enabled('paypal')], 'saved' => $saved,
            'updatedAt' => $record?->updated_at])->header('Cache-Control', 'no-store, private');
    }

    public function update(Request $request, GatewayConfiguration $config)
    {
        $rules = ['revision' => 'required|integer|min:0', 'mode' => 'required|in:sandbox,live', 'stripe_enabled' => 'required|boolean', 'paypal_enabled' => 'required|boolean',
            'helcim_enabled' => 'sometimes|boolean', 'credentials' => 'nullable|array:sandbox,live'];
        foreach (['sandbox', 'live'] as $mode) {
            $rules["credentials.$mode"] = 'sometimes|array:helcim,stripe,paypal';
            foreach (GatewayConfiguration::FIELDS as $provider => $fields) {
                $rules["credentials.$mode.$provider"] = 'sometimes|array:'.implode(',', array_keys($fields));
                foreach ($fields as $key => $label) {
                    $rules["credentials.$mode.$provider.$key"] = 'nullable|string|max:512|regex:/^\S+$/';
                }
            }
        }
        $data = $request->validate($rules);
        DB::transaction(function () use ($data, $config, $request) {
            $settings = GatewaySetting::lockForUpdate()->find(1);
            if (($settings?->revision ?? 0) !== (int) $data['revision']) {
                throw ValidationException::withMessages(['settings' => 'Settings changed. Reload this page before saving.']);
            }
            $values = $settings?->credentials ?? $config->allCredentials();
            foreach (['sandbox', 'live'] as $mode) {
                foreach (GatewayConfiguration::FIELDS as $provider => $fields) {
                    foreach ($fields as $key => $label) {
                        $value = $data['credentials'][$mode][$provider][$key] ?? null;
                        if ($value !== null && $value !== '') {
                            $values[$mode][$provider][$key] = $value;
                        }
                    }
                }
            }
            foreach (['sandbox', 'live'] as $mode) {
                $key = $values[$mode]['stripe']['secret'] ?? null;
                if ($key && ! str_starts_with($key, $mode === 'live' ? 'sk_live_' : 'sk_test_')) {
                    throw ValidationException::withMessages(['settings' => ($mode === 'live' ? 'Production' : 'UAT').' Stripe secret key has the wrong environment prefix.']);
                }
                $secret = $values[$mode]['stripe']['webhook_secret'] ?? null;
                if ($secret && ! str_starts_with($secret, 'whsec_')) {
                    throw ValidationException::withMessages(['settings' => 'Stripe webhook signing secrets must start with whsec_.']);
                }
            }
            foreach (GatewayConfiguration::FIELDS as $provider => $fields) {
                if ($data[$provider.'_enabled'] ?? false) {
                    foreach ($fields as $key => $label) {
                        if (empty($values[$data['mode']][$provider][$key])) {
                            throw ValidationException::withMessages(['settings' => 'Complete all '.ucfirst($provider).' credentials for the selected environment before enabling it.']);
                        }
                    }
                }
            }
            $settings ??= new GatewaySetting;
            $settings->id = 1;
            $settings->fill(['mode' => $data['mode'], 'helcim_enabled' => $data['helcim_enabled'] ?? false, 'stripe_enabled' => $data['stripe_enabled'], 'paypal_enabled' => $data['paypal_enabled'], 'credentials' => $values,
                'revision' => ($settings->revision ?? 0) + 1, 'updated_by' => $request->user()->id])->save();
        }, 3);

        return redirect()->route('admin.gateways.edit')->with('status', 'Gateway settings saved. New checkouts use the selected environment and enabled gateways.');
    }

    public function test(string $provider, string $mode, PaymentGateway $gateway)
    {
        abort_unless(isset(GatewayConfiguration::FIELDS[$provider]) && in_array($mode, ['sandbox', 'live'], true), 404);
        try {
            $gateway->testConnection($provider, $mode);
        } catch (\Throwable $e) {
            return back()->withErrors(['gateway' => 'Connection could not be verified. Check the saved credentials and provider account.']);
        }

        return back()->with('status', ucfirst($provider).' '.($mode === 'live' ? 'Production' : 'UAT').' API credentials accepted. Webhook delivery must still be tested with a sandbox/live transaction.');
    }
}
