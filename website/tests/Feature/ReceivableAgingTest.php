<?php

namespace Tests\Feature;

use App\Models\{Customer, GeneralSetting, Order, Payment, Role, User};
use App\Services\{CustomerInvoicing, DocumentTheme, ReceivableAging};
use App\Support\BusinessTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReceivableAgingTest extends TestCase
{
    use RefreshDatabase;

    private function customer(string $name): Customer
    {
        return User::factory()->create([
            'name' => $name, 'account_type' => 'individual',
            'role_id' => Role::where('name', 'Customer')->value('id'),
            'is_active' => true, 'email_verified_at' => now(),
        ])->customerRecord();
    }

    private function order(Customer $customer, array $attributes = []): Order
    {
        $order = Order::create($attributes + [
            'customer_id' => $customer->id, 'checkout_token' => Str::uuid(), 'number' => 'AR-'.Str::random(7),
            'first_name' => $customer->name, 'last_name' => 'Test', 'email' => $customer->email, 'phone' => '555123',
            'address' => 'Test Street', 'city' => 'Toronto', 'province' => 'ON', 'postal_code' => 'M5V', 'country' => 'Canada',
            'subtotal_cents' => 10000, 'delivery_cents' => 0, 'tax_cents' => 0, 'payment_method' => 'credit',
            'payment_status' => 'unpaid', 'status' => 'delivered', 'fulfillment' => 'pickup', 'currency' => 'CAD', 'source' => 'website',
        ]);
        $order->items()->create(['name' => 'Invoice snapshot cake', 'product_code' => 'CAKE', 'qr_code' => 'CAKE', 'quantity' => 1, 'unit_cents' => 10000, 'line_cents' => 10000]);

        return $order;
    }

    private function signInAdmin(): void
    {
        $this->actingAs(User::factory()->create(['is_active' => true, 'role_id' => Role::where('is_super', true)->value('id')]));
    }

    public function test_aging_and_invoice_summary_share_the_outstanding_invoice_balances(): void
    {
        $this->travelTo(BusinessTime::start('2026-11-24'));
        $buyer = $this->customer('Account Buyer');
        $buyer->update(['business_name' => 'Example Bakery']);
        $other = $this->customer('Other Buyer');
        $invoicing = app(CustomerInvoicing::class);
        $asOf = BusinessTime::today();

        foreach ([-1, 0, 1, 30, 31, 60, 61, 90, 91] as $age) {
            $order = $this->order($buyer);
            $invoicing->issue($order)->update(['issued_at' => $asOf->subDays($age)->endOfDay()->utc()]);
        }

        $order = $this->order($buyer, ['payment_method' => 'cheque', 'cheque_due_date' => '2026-10-10']);
        $invoice = $invoicing->issue($order);
        // Simulate existing invoices created before there was a dedicated due date column.
        $invoice->update(['issued_at' => BusinessTime::start('2026-09-21'), 'due_date' => null]);
        $order->settlements()->create(['method' => 'cash', 'amount_cents' => 2500, 'note' => 'Confirmed collection']);
        $order->update(['refund_credit_cents' => 500, 'cheque_due_date' => '2026-09-15']);
        $order->items()->update(['name' => 'Edited live product']);
        $invoicing->issue($this->order($buyer, ['payment_status' => 'paid']));
        $invoicing->issue($this->order($buyer))->order->update(['status' => 'cancelled']);
        $this->order($buyer);
        $invoicing->issue($this->order($other));
        $sandbox = $this->order($buyer, ['payment_method' => 'card']);
        $invoicing->issue($sandbox);
        Payment::create(['order_id' => $sandbox->id, 'reference' => (string) Str::uuid(), 'provider' => 'helcim', 'mode' => 'sandbox',
            'status' => 'pending', 'amount_cents' => 10000, 'currency' => 'CAD', 'expires_at' => now()->addHour()]);

        $this->signInAdmin();
        $report = $this->get(route('admin.reports.receivable-aging'))->assertOk()->assertSee('Example Bakery')->assertSee('Totals')->viewData('aging');
        $expected = ['current' => 20000, 'days_1_30' => 20000, 'days_31_60' => 27000, 'days_61_90' => 20000, 'days_91_plus' => 10000, 'total' => 97000];
        $this->assertSame($expected, $report['rows'][0]['amounts']);
        $this->assertSame(107000, $report['totals']['total']);
        $profile = $this->get(route('admin.customers.show', $buyer))->assertOk()->assertSee('A/R Aging')->viewData('aging');
        $this->assertSame($expected, $profile['totals']);
        $this->assertCount(1, $profile['rows']);
        $doc = app(DocumentTheme::class)->invoice($invoice->fresh());
        $this->assertSame([
            'Total Due' => '$70.00', 'Current Balance Owing' => '$970.00',
            '1–30 Days' => '$200.00', '31–60 Days' => '$270.00', '61–90 Days' => '$200.00', '91+ Days' => '$100.00',
        ], $doc['summary']);
        $this->get(route('admin.invoices.show', $invoice))->assertOk()->assertSee('Invoice snapshot cake')->assertDontSee('Edited live product')
            ->assertSee('21 Sep 2026')->assertSee('Oct 10, 2026')->assertDontSee('Other open order balances')
            ->assertSeeTextInOrder(['Payment status', 'Pickup Details', 'Product subtotal', 'Payment Methods'])
            ->assertDontSee('Delivery Details')->assertDontSee('Delivery / pickup details');
        $this->assertArrayNotHasKey('invoice_payment_methods', app(DocumentTheme::class)->order($order, $invoice));
    }

    public function test_new_invoices_keep_issue_and_due_dates_after_terms_change(): void
    {
        $this->travelTo(BusinessTime::start('2026-11-24')->addHours(22));
        $buyer = $this->customer('Terms Buyer');
        $this->signInAdmin();
        $this->post(route('admin.customers.terms', $buyer), [
            'revision' => $buyer->commercial_revision, 'credit_limit' => 0, 'overage' => 0,
            'credit_policy' => 'block', 'cheque_days' => 7, 'invoice_term_days' => 15,
        ])->assertSessionHasNoErrors();
        $order = $this->order($buyer);
        $invoicing = app(CustomerInvoicing::class);
        $invoice = $invoicing->issue($order);
        $this->assertSame('2026-12-09', $invoice->paymentDueDate());
        $buyer->update(['invoice_term_days' => 30]);
        $this->travel(20)->days();
        $reissued = $invoicing->issue($order);
        $this->assertTrue($invoice->issued_at->equalTo($reissued->issued_at));
        $this->assertSame('2026-12-09', $reissued->paymentDueDate());
        $this->assertSame(10000, app(ReceivableAging::class)->report($buyer->id)['totals']['days_1_30']);
    }

    public function test_authorized_settings_feed_signed_and_downloadable_invoices(): void
    {
        $this->signInAdmin();
        $settings = GeneralSetting::findOrFail(1);
        $form = ['revision' => $settings->revision, 'delivery' => '0', 'tax' => '0'];
        $this->put(route('admin.settings.update'), $form + ['invoice_eft_transit' => '01234'])->assertSessionHasErrors('invoice_eft_transit');
        $this->put(route('admin.settings.update'), $form + ['invoice_eft_transit' => '01234', 'invoice_eft_institution' => '001',
            'invoice_eft_account' => '000012345', 'etransfer_email' => 'remit@example.test', 'mailing_address' => "Test Mailing Address\nToronto"])->assertSessionHasNoErrors();
        $saved = $settings->fresh();
        $this->assertSame('000012345', $saved->invoice_eft_account);
        $this->assertNotSame('000012345', $saved->getRawOriginal('invoice_eft_account'));
        $this->assertArrayNotHasKey('invoice_eft_account', $saved->toArray());
        $buyer = $this->customer('Payment Details Buyer');
        $order = $this->order($buyer, ['fulfillment' => 'delivery', 'address' => 'Test delivery address']);
        $invoice = app(CustomerInvoicing::class)->issue($order);
        $order->update(['fulfillment' => 'pickup']);
        $this->actingAs($buyer->user);
        $this->get(URL::temporarySignedRoute('invoice.document', now()->addHour(), ['invoice' => $invoice]))->assertOk()
            ->assertSee('01234')->assertSee('000012345')->assertSee('remit@example.test')->assertSee('Test Mailing Address')
            ->assertSeeTextInOrder(['Payment status', 'Delivery Details', 'Test delivery address', 'Product subtotal', 'Payment Methods'])
            ->assertDontSee('Pickup Details')->assertDontSee('Delivery / pickup details');
        $pdf = $this->get(route('customer.documents.file', ['kind' => 'invoice', 'document' => $invoice->id, 'download' => 1]))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->get(route('customer.documents.file', ['kind' => 'order', 'document' => $order]))->assertOk()->assertDontSee('000012345');
    }
}
