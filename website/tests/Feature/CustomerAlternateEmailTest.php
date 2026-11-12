<?php

namespace Tests\Feature;

use App\Models\{Customer, EmailHistory, Order, Product, Role, SmtpSetting, User};
use App\Services\{CustomerEmails, CustomerInvoicing, EmailOutbox, OutgoingEmail, PosSale, SmtpConfiguration};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\{Event, Http};
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerAlternateEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    private function customer(array $extra = []): User
    {
        $user = User::factory()->create($extra + ['name' => 'Alternate Buyer', 'email' => 'primary@example.test',
            'phone' => '555123', 'account_type' => 'individual', 'is_active' => true,
            'role_id' => Role::where('name', 'Customer')->value('id')]);
        $user->customerRecord()->update(['alternate_email' => 'copy@example.test']);

        return $user;
    }

    private function admin(): User
    {
        return User::factory()->create(['is_active' => true, 'role_id' => Role::where('is_super', true)->value('id')]);
    }

    private function settings(string $method = 'resend'): SmtpSetting
    {
        return SmtpSetting::forceCreate(['id' => 1, 'enabled' => true, 'delivery_method' => $method,
            'api_key' => 'test-secret', 'from_address' => 'info@example.test', 'from_name' => 'Mamma Mia',
            'revision' => 1, 'tested_revision' => 1, 'tested_at' => now(), 'host' => 'smtp.example.test', 'port' => 587, 'encryption' => 'tls']);
    }

    private function order(Customer $customer, array $extra = []): Order
    {
        $order = Order::create($extra + ['customer_id' => $customer->id, 'checkout_token' => (string) Str::uuid(),
            'number' => 'mmc-'.Str::ulid(), 'first_name' => 'Alternate', 'last_name' => 'Buyer', 'email' => $customer->email,
            'phone' => '123', 'address' => '', 'city' => '', 'province' => '', 'postal_code' => '', 'country' => 'Canada',
            'subtotal_cents' => 1000, 'delivery_cents' => 0, 'tax_cents' => 0, 'currency' => 'CAD', 'fulfillment' => 'pickup',
            'pickup_address' => 'Our warehouse', 'payment_method' => 'cash', 'payment_status' => 'unpaid', 'status' => 'parked_admin']);
        $order->items()->create(['name' => 'Test Cake', 'product_code' => 'TEST', 'quantity' => 1, 'unit_cents' => 1000, 'line_cents' => 1000]);

        return $order;
    }

    public function test_customer_can_save_change_and_clear_alternate_without_changing_login_or_other_accounts(): void
    {
        $user = $this->customer();
        $other = $this->customer(['email' => 'other@example.test']);
        $before = $user->only(['email', 'email_verified_at', 'account_type', 'role_id']);
        $url = route('customer.profile.update');
        $this->actingAs($user)->get(route('customer.profile'))->assertOk()->assertSee('Alternate email address (optional)')->assertSee('copy@example.test');
        $this->put($url, ['name' => $user->name, 'phone' => $user->phone, 'alternate_email' => '  New.Copy@Example.test  ',
            'email' => 'hijack@example.test', 'email_verified_at' => null, 'user_id' => $other->id])->assertSessionHasNoErrors();
        $this->assertSame('new.copy@example.test', $user->customerRecord()->alternate_email);
        $this->assertEquals($before, $user->fresh()->only(array_keys($before)));
        $this->assertSame('copy@example.test', $other->customerRecord()->alternate_email);
        $this->put($url, ['name' => $user->name, 'phone' => $user->phone])->assertSessionHasNoErrors();
        $this->assertSame('new.copy@example.test', $user->customerRecord()->alternate_email);
        $this->put($url, ['name' => $user->name, 'phone' => $user->phone, 'alternate_email' => ''])->assertSessionHasNoErrors();
        $this->assertNull($user->customerRecord()->alternate_email);
        auth()->logout();
        $this->put($url, ['alternate_email' => 'attacker@example.test'])->assertRedirect(route('customer.login'));
    }

    public function test_admin_can_save_and_clear_alternate_with_existing_permissions(): void
    {
        $user = $this->customer();
        $customer = $user->customerRecord();
        $url = route('admin.customers.update', $customer);
        $this->actingAs($this->admin())->get(route('admin.customers.edit', $customer))->assertOk()->assertSee('name="alternate_email"', false);
        $this->put($url, ['name' => $customer->name, 'alternate_email' => ' Billing@Example.test '])->assertSessionHasNoErrors();
        $this->assertSame('billing@example.test', $customer->fresh()->alternate_email);
        $this->assertSame('primary@example.test', $user->fresh()->email);
        $this->get(route('admin.customers.index', ['search' => 'billing@example.test']))->assertOk()
            ->assertViewHas('customers', fn ($rows) => $rows->total() === 1 && $rows->first()->id === $customer->id);
        $this->put($url, ['name' => $customer->name, 'alternate_email' => null])->assertSessionHasNoErrors();
        $this->assertNull($customer->fresh()->alternate_email);
        $staff = User::factory()->create(['is_active' => true, 'role_id' => Role::create(['name' => 'Alternate reader', 'permissions' => ['orders.view']])->id]);
        $this->actingAs($staff)->put($url, ['name' => $customer->name, 'alternate_email' => 'unauthorized@example.test'])->assertForbidden();
        $this->assertNull($customer->fresh()->alternate_email);
    }

    public function test_invalid_alternates_are_rejected_by_both_editors_without_partial_saves(): void
    {
        $user = $this->customer();
        foreach ([$user, $this->admin()] as $actor) {
            $this->actingAs($actor);
            $url = $actor->isCustomer() ? route('customer.profile.update') : route('admin.customers.update', $user->customerRecord());
            foreach (['not-an-email', 'one@example.test,two@example.test', "copy@example.test\r\nBcc: other@example.test", str_repeat('a', 256).'@example.test', ['copy@example.test']] as $invalid) {
                $this->put($url, ['name' => 'Should not save', 'phone' => '123', 'alternate_email' => $invalid])->assertSessionHasErrors('alternate_email');
                $this->assertSame('copy@example.test', $user->customerRecord()->alternate_email);
                $this->assertSame('Alternate Buyer', $user->fresh()->name);
            }
        }
    }

    public function test_website_and_pos_orders_queue_both_addresses_once(): void
    {
        $user = $this->customer();
        $product = Product::create(['category_id' => 1, 'slug' => 'alternate-cake', 'premium_marketing_name' => 'Alternate Cake',
            'qr_code' => 'ALT1', 'is_active' => true, 'total_selling_price_cad' => 10]);
        $this->actingAs($user)->postJson(route('cart.add', $product), ['quantity' => 1])->assertOk();
        $this->get('/checkout')->assertOk();
        $data = ['checkout_token' => session('checkout_token'), 'first_name' => 'Alternate', 'last_name' => 'Buyer', 'email' => $user->email,
            'phone' => '555123', 'address' => '10 Test', 'city' => 'Toronto', 'province' => 'ON', 'postal_code' => 'M1M1M1', 'country' => 'Canada', 'payment_method' => 'cash'];
        $this->post('/checkout', $data)->assertRedirect('/order-success');
        $this->post('/checkout', $data)->assertRedirect('/order-success');
        $admin = $this->admin();
        $this->actingAs($admin);
        $service = app(PosSale::class);
        $quote = $service->quote([['id' => $product->id, 'quantity' => 1]], 'pickup', $admin->id, ['customer_id' => $user->customerRecord()->id]);
        $sale = ['customer_id' => $user->customerRecord()->id, 'payment_method' => 'cash', 'payment_status' => 'unpaid'];
        $service->complete($quote['quote'], $sale, $admin->id);
        $service->complete($quote['quote'], $sale, $admin->id);
        $entries = EmailHistory::where('type', 'order_confirmation')->get();
        $this->assertCount(2, $entries);
        $officeEntries = EmailHistory::where('type', 'new_order_notification')->get();
        $this->assertCount(2, $officeEntries);
        foreach ($officeEntries as $office) $this->assertSame(['info@mammamiacucina.ca'], $office->recipients());
        foreach ($entries as $entry) {
            $this->assertSame(['primary@example.test', 'copy@example.test'], $entry->recipients());
            $this->assertNotEmpty($entry->payload['document']);
        }
    }

    public function test_notifications_use_order_customer_and_manual_invoice_uses_same_rule(): void
    {
        $customer = $this->customer()->customerRecord();
        $order = $this->order($customer, ['email' => 'original-order@example.test', 'status' => 'ready_to_dispatch']);
        $this->customer(['email' => 'original-order@example.test'])->customerRecord()->update(['alternate_email' => 'wrong-customer@example.test']);
        foreach (['order_confirmation', 'order_status', 'payment_received'] as $type) {
            app(CustomerEmails::class)->order($order, $type, 'test:'.$type, 1000);
            $entry = EmailHistory::where('type', $type)->firstOrFail();
            $this->assertSame(['original-order@example.test', 'copy@example.test'], $entry->recipients());
        }
        $invoice = app(CustomerInvoicing::class)->issue($order);
        $this->actingAs($this->admin())->post(route('admin.invoices.email', $invoice))->assertSessionHasNoErrors();
        $this->post(route('admin.invoices.email', $invoice))->assertSessionHasNoErrors();
        $entry = EmailHistory::where('type', 'customer_invoice')->sole();
        $this->assertSame(['original-order@example.test', 'copy@example.test'], $entry->recipients());
        $this->assertStringContainsString('/invoice-document/', $entry->payload['body']);
    }

    public function test_outbox_retries_preserve_both_recipients_and_identical_pdf_after_profile_changes(): void
    {
        $this->settings();
        $customer = $this->customer()->customerRecord();
        $order = $this->order($customer);
        app(CustomerEmails::class)->order($order, 'order_confirmation', 'order-created:'.$order->id);
        $entry = EmailHistory::firstOrFail();
        Http::fake(['api.resend.com/*' => Http::sequence()->push([], 503)->push(['id' => 'accepted'], 200)]);
        $worker = app(EmailOutbox::class);
        $this->assertFalse($worker->deliver($entry->id));
        $customer->update(['alternate_email' => 'changed@example.test']);
        $this->travel(3)->minutes();
        $this->assertTrue($worker->deliver($entry->id));
        $this->assertFalse($worker->deliver($entry->id));
        $requests = Http::recorded();
        $this->assertCount(2, $requests);
        $this->assertSame(['primary@example.test', 'copy@example.test'], $requests[0][0]['to']);
        $this->assertSame($requests[0][0]->data(), $requests[1][0]->data());
        $this->assertSame($requests[0][0]->header('Idempotency-Key'), $requests[1][0]->header('Idempotency-Key'));
        $this->assertStringStartsWith('%PDF-', base64_decode($requests[0][0]['attachments'][0]['content']));
        $this->assertSame($requests[0][0]['attachments'][0]['content'], $entry->fresh()->archive['attachment']);
        $this->assertNull($entry->fresh()->payload);
        app(CustomerEmails::class)->order($order, 'order_status', 'next-event');
        $this->assertSame('changed@example.test', EmailHistory::where('event_key', 'next-event')->sole()->alternate_recipient);
    }

    public function test_queued_and_immediate_smtp_messages_include_the_copy_and_attachment(): void
    {
        $this->settings('smtp');
        $this->mock(SmtpConfiguration::class)->shouldReceive('apply')->andReturnNull();
        config(['mail.default' => 'configured_smtp', 'mail.mailers.configured_smtp' => ['transport' => 'array']]);
        $messages = [];
        Event::listen(MessageSending::class, function ($event) use (&$messages) { $messages[] = $event->message; });
        $user = $this->customer();
        $order = $this->order($user->customerRecord());
        app(CustomerEmails::class)->order($order, 'payment_received', 'smtp-receipt', 1000);
        $this->assertTrue(app(EmailOutbox::class)->deliver(EmailHistory::firstOrFail()->id));
        app(OutgoingEmail::class)->raw('An account update', fn ($m) => $m->to($user->email)->subject('Update'), 'account_update', $user);
        $this->assertCount(2, $messages);
        foreach ($messages as $message) {
            $this->assertSame(['primary@example.test', 'copy@example.test'], array_map(fn ($a) => $a->getAddress(), $message->getTo()));
        }
        $this->assertCount(1, $messages[0]->getAttachments());
        $this->assertSame('application', $messages[0]->getAttachments()[0]->getMediaType());
    }

    public function test_direct_api_sends_copy_but_all_security_templates_and_smtp_tests_remain_primary_only(): void
    {
        $this->settings();
        Http::fake(['api.resend.com/*' => Http::response(['id' => 'message'])]);
        $user = $this->customer();
        app(OutgoingEmail::class)->template('business_active', $user->email, ['account_url' => route('customer.login')], $user);
        Http::assertSent(fn ($r) => $r['to'] === ['primary@example.test', 'copy@example.test']);
        $entry = app(OutgoingEmail::class)->raw('Customer notice', fn ($m) => $m->to($user->email)->subject('Notice'), 'customer_notice');
        $this->assertSame('copy@example.test', $entry->alternate_recipient);
        foreach (['verification', 'verification_resend', 'business_verification', 'admin_invitation', 'pos_invitation', 'admin_verification', 'password_reset', 'business_approved', 'smtp_test'] as $type) {
            $this->travel(11)->minutes();
            $entry = app(OutgoingEmail::class)->template($type, $user->email, ['verification_url' => 'https://example.test/verify',
                'reset_url' => 'https://example.test/reset', 'expires_minutes' => 60], $user);
            $this->assertNull($entry->alternate_recipient, $type);
            $requests = Http::recorded();
            $this->assertSame(['primary@example.test'], $requests->last()[0]['to'], $type);
        }
    }

    public function test_business_approval_with_verification_is_primary_only_but_activation_copies_alternate(): void
    {
        $user = $this->customer(['account_type' => 'business', 'email_verified_at' => null]);
        $user->forceFill(['business_approved_at' => now()])->save();
        $this->assertSame(['primary@example.test'], EmailHistory::where('type', 'business_approved')->sole()->recipients());
        $user->forceFill(['email_verified_at' => now()])->save();
        $this->assertSame(['primary@example.test', 'copy@example.test'], EmailHistory::where('type', 'business_active')->sole()->recipients());
    }

    public function test_blank_invalid_duplicate_and_unmatched_alternates_never_add_a_recipient(): void
    {
        $customer = $this->customer()->customerRecord();
        foreach ([null, '', 'not-an-email', 'PRIMARY@EXAMPLE.TEST'] as $index => $alternate) {
            $customer->update(['alternate_email' => $alternate]);
            app(CustomerEmails::class)->queue('edge-'.$index, 'business_active', $customer->email, []);
            $this->assertSame(['primary@example.test'], EmailHistory::where('event_key', 'edge-'.$index)->sole()->recipients());
        }
        $customer->update(['alternate_email' => 'copy@example.test']);
        app(CustomerEmails::class)->queue('with-copy', 'business_active', $customer->email, []);
        $this->assertSame('copy@example.test', EmailHistory::where('event_key', 'with-copy')->sole()->alternate_recipient);
        $customer->update(['alternate_email' => null]);
        app(CustomerEmails::class)->queue('after-clearing', 'business_active', $customer->email, []);
        $this->assertNull(EmailHistory::where('event_key', 'after-clearing')->sole()->alternate_recipient);
        $customer->update(['alternate_email' => 'copy@example.test']);
        app(CustomerEmails::class)->queue('unmatched', 'business_active', 'unmatched@example.test', []);
        app(CustomerEmails::class)->queue('no-recursion', 'business_active', 'copy@example.test', []);
        $this->assertNull(EmailHistory::where('event_key', 'unmatched')->sole()->alternate_recipient);
        $this->assertNull(EmailHistory::where('event_key', 'no-recursion')->sole()->alternate_recipient);
    }

    public function test_alternate_cannot_log_in_reset_password_or_verify_primary(): void
    {
        $user = $this->customer(['email_verified_at' => null, 'password' => 'password-12345']);
        config(['mail.default' => 'array']);
        $this->post(route('customer.login.store'), ['email' => 'copy@example.test', 'password' => 'password-12345'])->assertSessionHasErrors('email');
        $this->post(route('customer.password.send'), ['email' => 'copy@example.test'])->assertSessionHas('status');
        $this->assertDatabaseCount('email_history', 0);
        $this->post(route('customer.password.send'), ['email' => $user->email])->assertSessionHas('status');
        $this->assertSame(['primary@example.test'], EmailHistory::where('type', 'password_reset')->sole()->recipients());
        $this->actingAs($user)->get(\Illuminate\Support\Facades\URL::temporarySignedRoute('customer.verify', now()->addHour(),
            ['id' => $user->id, 'hash' => sha1('copy@example.test')]))->assertForbidden();
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_history_displays_and_searches_saved_recipients_and_old_entries_stay_primary_only(): void
    {
        $customer = $this->customer()->customerRecord();
        app(CustomerEmails::class)->queue('history', 'business_active', $customer->email, []);
        $entry = EmailHistory::firstOrFail();
        $this->actingAs($this->admin())->get(route('admin.email.history', ['search' => 'copy@example.test']))->assertOk()
            ->assertSee('primary@example.test')->assertSee('copy@example.test')->assertViewHas('emails', fn ($rows) => $rows->total() === 1);
        $this->get(route('admin.email.history.show', $entry))->assertOk()->assertSee('copy@example.test');
        $entry->update(['alternate_recipient' => null]); // Represents a pre-upgrade queued message.
        $this->settings();
        Http::fake(['api.resend.com/*' => Http::response(['id' => 'legacy'])]);
        $this->assertTrue(app(EmailOutbox::class)->deliver($entry->id));
        Http::assertSent(fn ($r) => $r['to'] === ['primary@example.test']);
    }
}
