<?php

namespace Tests\Feature;

use App\Models\GatewaySetting;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Services\GatewayConfiguration;
use App\Services\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GatewaySettingsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(bool $allowed = true): void
    {
        $role = $allowed ? Role::where('is_super', true)->firstOrFail() : Role::create(['name' => 'Settings editor', 'permissions' => ['settings.manage']]);
        $this->actingAs(User::factory()->create(['role_id' => $role->id, 'is_active' => true]));
    }

    private function data(): array
    {
        return ['revision' => 0, 'mode' => 'sandbox', 'stripe_enabled' => true, 'paypal_enabled' => true, 'credentials' => [
            'sandbox' => ['stripe' => ['secret' => 'sk_test_sandbox_key', 'webhook_secret' => 'whsec_sandbox'], 'paypal' => ['client_id' => 'sandbox-client', 'secret' => 'sandbox-secret', 'webhook_id' => 'sandbox-webhook']],
            'live' => ['stripe' => ['secret' => 'sk_live_production_key', 'webhook_secret' => 'whsec_production'], 'paypal' => ['client_id' => 'production-client', 'secret' => 'production-secret', 'webhook_id' => 'production-webhook']],
        ]];
    }

    public function test_settings_are_encrypted_masked_and_blank_fields_preserve_credentials(): void
    {
        $this->admin();
        $this->put(route('admin.gateways.update'), $this->data())->assertSessionHasNoErrors()->assertRedirect(route('admin.gateways.edit'));
        $raw = DB::table('gateway_settings')->value('credentials');
        $this->assertStringNotContainsString('sk_test_sandbox_key', $raw);
        $this->assertSame('sk_test_sandbox_key', GatewaySetting::first()->credentials['sandbox']['stripe']['secret']);
        $this->assertArrayNotHasKey('credentials', GatewaySetting::first()->toArray());
        $this->get(route('admin.gateways.edit'))->assertOk()->assertSee('Gateway Settings')->assertSee('UAT / Sandbox')->assertDontSee('sk_test_sandbox_key')->assertDontSee('production-secret');
        $this->put(route('admin.gateways.update'), ['revision' => 1, 'mode' => 'live', 'stripe_enabled' => 1, 'paypal_enabled' => 0, 'credentials' => ['live' => ['stripe' => ['secret' => '']]]])->assertSessionHasNoErrors();
        $this->assertSame('live', app(GatewayConfiguration::class)->mode());
        $this->assertSame('sk_live_production_key', app(GatewayConfiguration::class)->credentials('stripe', 'live')['secret']);
        $this->assertTrue(app(PaymentGateway::class)->ready('stripe'));
        $this->assertFalse(app(PaymentGateway::class)->ready('paypal'));
    }

    public function test_permissions_protect_read_update_and_connection_tests(): void
    {
        $this->admin(false);
        $this->get(route('admin.gateways.edit'))->assertForbidden();
        $this->put(route('admin.gateways.update'), $this->data())->assertForbidden();
        $this->post(route('admin.gateways.test', ['stripe', 'sandbox']))->assertForbidden();
        $this->assertDatabaseCount('gateway_settings', 0);
    }

    public function test_invalid_settings_do_not_flash_secrets_or_enable_incomplete_provider(): void
    {
        $this->admin();
        $data = $this->data();
        $data['credentials']['live']['stripe']['secret'] = 'sk_test_wrong_environment';
        $this->from(route('admin.gateways.edit'))->put(route('admin.gateways.update'), $data)->assertSessionHasErrors('settings');
        $this->assertArrayNotHasKey('credentials', session()->getOldInput());
        $this->assertDatabaseCount('gateway_settings', 0);
        $data = $this->data();
        $data['credentials']['sandbox']['paypal']['secret'] = '';
        $this->put(route('admin.gateways.update'), $data)->assertSessionHasErrors('settings');
        $this->assertDatabaseCount('gateway_settings', 0);
        $data = $this->data();
        $data['credentials']['sandbox']['stripe']['secret'] = str_repeat('s', 600);
        $this->put(route('admin.gateways.update'), $data)->assertSessionHasErrors();
        $this->assertArrayNotHasKey('credentials', session()->getOldInput());
    }

    public function test_stale_updates_cannot_overwrite_settings(): void
    {
        $this->admin();
        $this->put(route('admin.gateways.update'), $this->data())->assertSessionHasNoErrors();
        $data = $this->data();
        $data['mode'] = 'live';
        $this->put(route('admin.gateways.update'), $data)->assertSessionHasErrors('settings');
        $this->assertSame('sandbox', GatewaySetting::first()->mode);
    }

    public function test_saved_connection_tests_use_correct_environment_without_charging(): void
    {
        $this->admin();
        $this->put(route('admin.gateways.update'), $this->data())->assertSessionHasNoErrors();
        Http::preventStrayRequests();
        Http::fake(['api.stripe.com/v1/balance' => Http::response(['available' => []]), 'api-m.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'not-exposed'])]);
        $this->post(route('admin.gateways.test', ['stripe', 'live']))->assertSessionHasNoErrors();
        $this->post(route('admin.gateways.test', ['paypal', 'live']))->assertSessionHasNoErrors();
        Http::assertSent(fn ($r) => $r->url() === 'https://api.stripe.com/v1/balance' && $r->hasHeader('Authorization', 'Bearer sk_live_production_key'));
        Http::assertSent(fn ($r) => $r->url() === 'https://api-m.paypal.com/v1/oauth2/token');
        Http::assertSentCount(2);
    }

    public function test_disabling_and_switching_preserves_old_environment_webhooks(): void
    {
        $this->admin();
        $this->put(route('admin.gateways.update'), $this->data())->assertSessionHasNoErrors();
        $this->put(route('admin.gateways.update'), ['revision' => 1, 'mode' => 'live', 'stripe_enabled' => 0, 'paypal_enabled' => 0])->assertSessionHasNoErrors();
        $this->assertFalse(app(PaymentGateway::class)->ready('stripe'));
        $event = ['id' => 'evt_old_uat', 'livemode' => false, 'data' => ['object' => []]];
        $body = json_encode($event);
        $timestamp = time();
        $signature = 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_sandbox');
        $this->call('POST', '/payments/webhooks/stripe?mode=sandbox', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $signature], $body)->assertOk();
        $this->assertDatabaseHas('payment_events', ['event_id' => 'evt_old_uat', 'mode' => 'sandbox']);
        $this->call('POST', '/payments/webhooks/stripe?mode=live', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $signature], $body)->assertStatus(400);
    }

    public function test_disabled_provider_can_retrieve_existing_payment_using_original_credentials(): void
    {
        $this->admin();
        $this->put(route('admin.gateways.update'), $this->data())->assertSessionHasNoErrors();
        $this->put(route('admin.gateways.update'), ['revision' => 1, 'mode' => 'live', 'stripe_enabled' => 0, 'paypal_enabled' => 0])->assertSessionHasNoErrors();
        Http::preventStrayRequests();
        Http::fake(['api.stripe.com/v1/checkout/sessions/*' => Http::response(['id' => 'cs_existing'])]);
        $payment = new Payment(['mode' => 'sandbox', 'provider' => 'stripe', 'provider_order_id' => 'cs_existing']);
        $this->assertSame('cs_existing',app(PaymentGateway::class)->retrieve($payment)['id']);
        Http::assertSent(fn ($r) => $r->hasHeader('Authorization','Bearer sk_test_sandbox_key'));
    }
}
