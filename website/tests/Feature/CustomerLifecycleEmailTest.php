<?php

namespace Tests\Feature;

use App\Models\EmailHistory;
use App\Models\Order;
use App\Models\Payment;
use App\Models\SmtpSetting;
use App\Models\User;
use App\Services\CustomerEmails;
use App\Services\EmailOutbox;
use App\Services\OrderAmendment;
use App\Services\OutgoingEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CustomerLifecycleEmailTest extends TestCase
{
    use RefreshDatabase;

    private function settings(): SmtpSetting
    {
        return SmtpSetting::forceCreate(['id' => 1, 'enabled' => true, 'delivery_method' => 'resend', 'api_key' => 'test-secret', 'from_address' => 'info@example.test', 'from_name' => 'Mamma Mia', 'revision' => 1, 'tested_revision' => 1, 'tested_at' => now(), 'host' => 'smtp.example.test', 'port' => 587, 'encryption' => 'tls']);
    }

    private function order(array $extra = []): Order
    {
        return Order::create($extra + ['checkout_token' => (string) Str::uuid(), 'number' => 'mmc-'.random_int(1000, 9999), 'first_name' => 'Buyer', 'last_name' => 'Test', 'email' => 'buyer@example.test', 'phone' => '123', 'address' => '123 Street', 'city' => 'Toronto', 'province' => 'ON', 'postal_code' => 'M5V 2T6', 'country' => 'Canada', 'subtotal_cents' => 10000, 'delivery_cents' => 0, 'tax_cents' => 0, 'currency' => 'CAD', 'fulfillment' => 'pickup', 'pickup_address' => 'Our warehouse', 'payment_method' => 'cash', 'payment_status' => 'unpaid', 'status' => 'warehouse_pending']);
    }

    public function test_approval_and_verification_in_either_order_send_one_active_email(): void
    {
        $u = User::factory()->create(['account_type' => 'business', 'is_active' => true, 'email_verified_at' => null, 'business_approved_at' => null]);
        $u->forceFill(['business_approved_at' => now()])->save();
        $this->assertDatabaseHas('email_history', ['type' => 'business_approved', 'status' => 'queued']);
        $this->assertStringContainsString('Verify your email', EmailHistory::first()->payload['body']);
        $u->forceFill(['email_verified_at' => now()])->save();
        app(CustomerEmails::class)->business($u);
        $this->assertSame(1, EmailHistory::where('type', 'business_active')->count());
        $other = User::factory()->create(['account_type' => 'business', 'is_active' => true, 'email_verified_at' => null, 'business_approved_at' => null]);
        $other->forceFill(['email_verified_at' => now()])->save();
        $this->assertSame(2, EmailHistory::count());
        $other->forceFill(['business_approved_at' => now()])->save();
        $this->assertSame(2, EmailHistory::where('type', 'business_active')->count());
    }

    public function test_business_welcome_uses_review_template_and_existing_cooldown(): void
    {
        $this->settings();
        Http::fake(['api.resend.com/*' => Http::response(['id' => 'welcome'])]);
        $u = User::factory()->create(['account_type' => 'business', 'email_verified_at' => null]);
        app(OutgoingEmail::class)->template('verification', $u->email, ['verification_url' => 'https://example.test/verify', 'expires_minutes' => 60], $u);
        Http::assertSent(fn ($r) => str_contains($r['text'], 'under review') && str_contains($r['text'], 'https://example.test/verify'));
        $this->assertDatabaseHas('email_history', ['type' => 'business_verification', 'status' => 'accepted']);
        $this->expectException(ValidationException::class);
        app(OutgoingEmail::class)->template('verification', $u->email, [], $u);
    }

    public function test_immutable_pdf_snapshot_and_provider_retry_are_deduplicated(): void
    {
        $this->settings();
        $order = $this->order();
        $order->items()->create(['name' => 'Cake & cream', 'product_code' => 'ABC-1', 'quantity' => 2, 'unit_cents' => 5000, 'line_cents' => 10000]);
        $service = app(CustomerEmails::class);
        $service->order($order, 'order_confirmation', 'order-created:'.$order->id);
        $service->order($order, 'order_confirmation', 'order-created:'.$order->id);
        $entry = EmailHistory::firstOrFail();
        $this->assertSame(1, EmailHistory::count());
        $this->assertStringContainsString('Amount due: $100.00', $entry->payload['body']);
        $this->assertStringNotContainsString('Buyer', DB::table('email_history')->value('payload'));
        $order->update(['subtotal_cents' => 8000]);
        $this->assertSame('$100.00 CAD', $entry->payload['document']['values']['total']);
        Http::fake(['api.resend.com/*' => Http::sequence()->push([], 503)->push(['id' => 'accepted-id'], 200)]);
        $worker = app(EmailOutbox::class);
        $this->assertFalse($worker->deliver($entry->id));
        $this->assertSame('retry', $entry->fresh()->status);
        $this->travel(3)->minutes();
        $this->assertTrue($worker->deliver($entry->id));
        $this->assertFalse($worker->deliver($entry->id));
        Http::assertSentCount(2);
        $requests = Http::recorded();
        $this->assertSame($requests[0][0]->header('Idempotency-Key'), $requests[1][0]->header('Idempotency-Key'));
        $this->assertSame($requests[0][0]->data(), $requests[1][0]->data());
        $this->assertStringStartsWith('%PDF-', base64_decode($requests[0][0]['attachments'][0]['content']));
        $this->assertNull($entry->fresh()->payload);
    }

    public function test_status_changes_only_and_verified_collections_not_receipt_uploads(): void
    {
        $order = $this->order(['payment_method' => 'etransfer', 'status' => 'transfer_pending', 'manual_collected_cents' => 0]);
        $order->update(['transfer_receipt_path' => 'receipt.pdf', 'revision' => 1]);
        $this->assertSame(0, EmailHistory::count());
        $staff = User::factory()->create();
        app(OrderAmendment::class)->settle($order, ['revision' => 1, 'direction' => 'collect', 'method' => 'etransfer', 'amount' => '40.00', 'reference' => 'TEST1', 'note' => 'Verified in bank', 'receipt_path' => 'receipt.pdf'], $staff->id);
        $this->assertSame(1, EmailHistory::where('type', 'payment_received')->count());
        $this->assertStringContainsString('Amount due: $60.00', EmailHistory::first()->payload['body']);
        app(OrderAmendment::class)->settle($order->fresh(), ['revision' => 2, 'direction' => 'collect', 'method' => 'etransfer', 'amount' => '60.00', 'reference' => 'TEST2', 'note' => 'Verified in bank', 'receipt_path' => 'receipt.pdf'], $staff->id);
        $this->assertSame(2, EmailHistory::where('type', 'payment_received')->count());
        $this->assertSame(1, EmailHistory::where('type', 'order_status')->count());
        $order = $order->fresh();
        $order->update(['notes' => 'Internal note']);
        $order->update(['status' => 'warehouse_pending']);
        $this->assertSame(3, EmailHistory::count());
    }

    public function test_transaction_rollback_removes_queued_events_and_unknown_total_is_not_zero(): void
    {
        $order = $this->order(['delivery_cents' => null]);
        DB::beginTransaction();
        app(CustomerEmails::class)->order($order, 'order_confirmation', 'rollback');
        $this->assertStringContainsString('To be confirmed', EmailHistory::first()->payload['body']);
        DB::rollBack();
        $this->assertSame(0, EmailHistory::count());
    }

    public function test_sandbox_email_is_labelled_and_gateway_paid_transition_not_repeated(): void
    {
        $order = $this->order(['status' => 'awaiting_payment', 'payment_method' => 'card', 'payment_status' => 'pending']);
        Payment::create(['order_id' => $order->id, 'reference' => (string) Str::uuid(), 'provider' => 'helcim', 'mode' => 'sandbox', 'status' => 'paid', 'amount_cents' => 10000, 'currency' => 'CAD', 'paid_at' => now(), 'expires_at' => now()->addHour()]);
        $order->update(['payment_status' => 'paid', 'status' => 'warehouse_pending', 'revision' => 1]);
        $order->update(['payment_status' => 'paid', 'status' => 'warehouse_pending']);
        $this->assertSame(1, EmailHistory::where('type', 'payment_received')->count());
        $this->assertStringContainsString('TEST ORDER', EmailHistory::where('type', 'payment_received')->first()->payload['body']);
    }

    public function test_disabled_delivery_waits_and_changed_credentials_do_not_retry_uncertain_mail(): void
    {
        $settings = $this->settings();
        $settings->update(['enabled' => false]);
        Http::fake(['api.resend.com/*' => Http::response([], 500)]);
        app(CustomerEmails::class)->order($this->order(), 'order_status', 'disabled');
        $entry = EmailHistory::first();
        $worker = app(EmailOutbox::class);
        $this->assertFalse($worker->deliver($entry->id));
        Http::assertNothingSent();
        $settings->update(['enabled' => true]);
        $worker->deliver($entry->id);
        $this->travel(3)->minutes();
        $settings->update(['api_key' => 'different-account']);
        $this->assertFalse($worker->deliver($entry->id));
        $this->assertSame('needs_review',$entry->fresh()->status);
        Http::assertSentCount(1);
    }
}
