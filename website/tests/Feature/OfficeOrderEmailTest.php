<?php

namespace Tests\Feature;

use App\Models\{EmailHistory, EmailTemplate, Order, SmtpSetting, User};
use App\Services\{CustomerEmails, EmailOutbox, SmtpConfiguration};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\{DB, Event, Http};
use Illuminate\Support\Str;
use Tests\TestCase;

class OfficeOrderEmailTest extends TestCase
{
    use RefreshDatabase;

    private function order(array $extra = []): Order
    {
        $order = Order::create($extra + ['checkout_token' => Str::uuid(), 'number' => 'OFFICE-'.Str::uuid(), 'source' => 'website',
            'first_name' => 'Office', 'last_name' => 'Buyer', 'email' => 'buyer@example.test', 'phone' => '123', 'address' => '',
            'city' => '', 'province' => '', 'postal_code' => '', 'country' => '', 'subtotal_cents' => 1000, 'delivery_cents' => 0,
            'tax_cents' => 0, 'fulfillment' => 'pickup', 'pickup_address' => 'Warehouse', 'payment_method' => 'cash',
            'payment_status' => 'unpaid', 'status' => 'parked_admin']);
        $order->items()->create(['name' => 'Office test cake', 'quantity' => 1, 'unit_cents' => 1000, 'line_cents' => 1000]);
        return $order;
    }

    private function settings(string $method = 'resend'): void
    {
        SmtpSetting::forceCreate(['id' => 1, 'enabled' => true, 'delivery_method' => $method, 'api_key' => 'test-secret',
            'from_address' => 'sender@example.test', 'from_name' => 'MMC', 'revision' => 1, 'tested_revision' => 1,
            'tested_at' => now(), 'host' => 'smtp.example.test', 'port' => 587, 'encryption' => 'tls']);
    }

    public function test_one_private_office_alert_per_new_website_or_pos_order_with_pdf(): void
    {
        $account = User::factory()->create(['email' => 'info@mammamiacucina.ca', 'account_type' => 'individual']);
        $account->customerRecord()->update(['alternate_email' => 'must-not-receive@example.test']);
        foreach (['website', 'pos'] as $source) {
            $order = $this->order(['source' => $source]);
            $service = app(CustomerEmails::class);
            $service->order($order, 'order_confirmation', 'order-created:'.$order->id);
            $service->order($order, 'order_confirmation', 'order-created:'.$order->id);
            $office = EmailHistory::where('event_key', 'order-office:'.$order->id)->sole();
            $customer = EmailHistory::where('event_key', 'order-created:'.$order->id)->sole();
            $this->assertEquals($order->id, $office->order_id);
            $this->assertSame(['info@mammamiacucina.ca'], $office->recipients());
            $this->assertSame('new_order_notification', $office->type);
            $this->assertStringContainsString(route('admin.orders.show', $order), $office->payload['body']);
            $this->assertStringNotContainsString(route('admin.orders.show', $order), $customer->payload['body']);
            $this->assertEquals($customer->payload['document'], $office->payload['document']);
            $service->order($order, 'order_status', 'order-status:'.$order->id.':1');
            $service->order($order, 'payment_received', 'order-paid:'.$order->id, 1000);
            $this->assertSame(1, EmailHistory::where('event_key', 'order-office:'.$order->id)->count());
        }
    }

    public function test_office_alert_does_not_depend_on_a_customer_email_and_rolls_back_with_order_transaction(): void
    {
        $order = $this->order(['email' => '']);
        DB::beginTransaction();
        app(CustomerEmails::class)->order($order, 'order_confirmation', 'order-created:'.$order->id);
        $this->assertSame(['info@mammamiacucina.ca'], EmailHistory::sole()->recipients());
        $this->assertStringContainsString('Customer email: Not provided', EmailHistory::sole()->payload['body']);
        DB::rollBack();
        $this->assertDatabaseCount('email_history', 0);
    }

    public function test_office_delivery_retries_same_payload_once_and_retains_pdf(): void
    {
        $this->settings();
        $order = $this->order();
        app(CustomerEmails::class)->order($order, 'order_confirmation', 'order-created:'.$order->id);
        $office = EmailHistory::where('type', 'new_order_notification')->sole();
        Http::fake(['api.resend.com/*' => Http::sequence()->push([], 503)->push(['id' => 'office-accepted'], 200)]);
        $outbox = app(EmailOutbox::class);
        $this->assertFalse($outbox->deliver($office->id));
        $this->travel(3)->minutes();
        $this->assertTrue($outbox->deliver($office->id));
        $this->assertFalse($outbox->deliver($office->id));
        $requests = Http::recorded();
        $this->assertCount(2, $requests);
        $this->assertSame(['info@mammamiacucina.ca'], $requests[0][0]['to']);
        $this->assertSame($requests[0][0]->data(), $requests[1][0]->data());
        $this->assertSame($requests[0][0]->header('Idempotency-Key'), $requests[1][0]->header('Idempotency-Key'));
        $this->assertStringStartsWith('%PDF-', base64_decode($requests[0][0]['attachments'][0]['content']));
        $this->assertSame('accepted', $office->fresh()->status);
    }

    public function test_office_smtp_delivery_uses_same_recipient_and_attachment(): void
    {
        $this->settings('smtp');
        $this->mock(SmtpConfiguration::class)->shouldReceive('apply')->andReturnNull();
        config(['mail.mailers.configured_smtp' => ['transport' => 'array']]);
        $messages = [];
        Event::listen(MessageSending::class, function ($event) use (&$messages) { $messages[] = $event->message; });
        $order = $this->order();
        app(CustomerEmails::class)->order($order, 'order_confirmation', 'order-created:'.$order->id);
        $this->assertTrue(app(EmailOutbox::class)->deliver(EmailHistory::where('type', 'new_order_notification')->sole()->id));
        $this->assertCount(1, $messages);
        $this->assertSame('info@mammamiacucina.ca', $messages[0]->getTo()[0]->getAddress());
        $this->assertCount(1, $messages[0]->getAttachments());
    }

    public function test_template_migration_preserves_customization_and_does_not_backfill_orders(): void
    {
        $this->order();
        $template = EmailTemplate::findOrFail('new_order_notification');
        $template->update(['subject' => 'Our saved subject', 'revision' => 3]);
        $migration = require database_path('migrations/2026_11_12_130000_add_office_order_email_template.php');
        $migration->up();
        $this->assertSame('Our saved subject', $template->fresh()->subject);
        $this->assertSame(3, $template->fresh()->revision);
        $this->assertDatabaseCount('email_history', 0);
    }
}
