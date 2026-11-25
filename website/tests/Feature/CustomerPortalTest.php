<?php

namespace Tests\Feature;

use App\Models\CustomerInvoice;
use App\Models\GatewaySetting;
use App\Models\InvoicePayment;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\CustomerInvoicing;
use App\Support\BusinessTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_sections_use_the_customers_orders_invoice_ages_and_credit(): void
    {
        $buyer = $this->customer();
        $buyer->customerRecord()->update(['credit_limit_cents' => 50000, 'order_discount_bps' => 750]);
        $date = BusinessTime::today()->subDays(14)->toDateString();
        $unpaid = $this->invoice($buyer, 15000, 'UNPAID-PORTAL');
        $unpaid->update(['issued_at' => BusinessTime::start($date)->addHours(12), 'due_date' => $date]);
        $paid = $this->invoice($buyer, 8500, 'PAID-PORTAL');
        $paid->order->updateQuietly(['payment_status' => 'paid', 'manual_collected_cents' => 8500]);
        $other = $this->invoice($this->customer(), 20000, 'OTHER-PORTAL');
        $open = $this->order($buyer, 1000, 'OPEN-PORTAL', 'parked_admin');

        $this->get(route('customer.dashboard'))->assertRedirect(route('customer.login'));
        $this->actingAs($buyer)->get(route('customer.dashboard'))->assertOk()->assertSee('Outstanding invoices')->assertSee('CAD $150.00');
        $this->get(route('customer.credit'))->assertOk()->assertSee('CAD $500.00')->assertSee('CAD $340.00')->assertSee('7.5%');
        $this->get(route('customer.orders', ['view' => 'open']))->assertOk()->assertSee($open->number)->assertDontSee($paid->order->number);
        $this->get(route('customer.orders', ['view' => 'closed']))->assertOk()->assertSee($unpaid->order->number)->assertDontSee($open->number);
        $this->get(route('customer.orders', ['view' => 'paid']))->assertOk()->assertSee($paid->order->number)->assertDontSee($unpaid->order->number);
        $this->get(route('customer.orders', ['to' => $date]))->assertOk()->assertDontSee($open->number);
        $this->get(route('customer.invoices'))->assertOk()->assertSee('14 days outstanding')->assertSee('14 days overdue')->assertDontSee($other->label);
        $this->get(route('customer.documents.file', ['invoice', $other->id]))->assertNotFound();
        $this->get(route('customer.receipts'))->assertOk()->assertSee('Payment receipt');
    }

    public function test_credit_warning_uses_the_allowance_and_the_statement_is_a_private_pdf(): void
    {
        $buyer = $this->customer();
        $buyer->customerRecord()->update(['credit_limit_cents' => 25000, 'overage_cents' => 5000]);
        $invoice = $this->invoice($buyer, 23999, 'STATEMENT-TEST');
        $this->get(route('customer.statement'))->assertRedirect(route('customer.login'));
        $this->actingAs($buyer)->get(route('customer.credit'))->assertOk()->assertSee('CAD $60.01')->assertDontSee('approaching your credit limit');
        $invoice->order->update(['subtotal_cents' => 24000]);
        $this->get(route('customer.credit'))->assertOk()->assertSee('80.0% used')->assertSee('approaching your credit limit');
        $this->get(route('customer.invoices'))->assertOk()->assertSee(route('customer.documents.file', ['invoice', $invoice->id, 'download' => 1]));
        $pdf = $this->get(route('customer.statement'))->assertOk()->assertHeader('Content-Type', 'application/pdf')
            ->assertDownload('account-statement-'.$buyer->customerRecord()->id.'-'.BusinessTime::today()->toDateString().'.pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->actingAs($this->admin())->get(route('customer.statement'))->assertForbidden();
    }

    public function test_reorder_prefills_available_lines_and_the_timeline_hides_staff_notes(): void
    {
        $buyer = $this->customer();
        $order = $this->order($buyer, 5000, 'REORDER-TEST', 'parked_admin');
        $product = Product::create(['category_id' => 1, 'is_active' => true, 'total_selling_price_cad' => 20,
            'slug' => 'test-reorder-product', 'qr_code' => 'REORDER', 'premium_marketing_name' => 'Reorder Cake']);
        $product->inventory()->create(['quantity_on_hand' => 4]);
        $order->items()->create(['product_id' => $product->id, 'name' => 'Reorder Cake', 'quantity' => 5, 'unit_cents' => 1000, 'line_cents' => 5000]);
        $order->items()->create(['name' => 'Retired legacy item', 'quantity' => 1, 'unit_cents' => 0, 'line_cents' => 0]);
        $order->events()->create(['description' => 'Status: parked_admin → warehouse_pending; payment: unpaid → unpaid · Internal notes', 'created_at' => now()->subSecond()]);
        $order->update(['status' => 'packing']);
        $this->actingAs($buyer)->get(route('customer.order', $order))->assertOk()
            ->assertSeeInOrder(['Order status history', 'Sent to warehouse', 'Packing'])->assertDontSee('Internal notes');
        $preview = route('customer.catalogue', ['reorder' => 1]);
        $this->withSession(['storefront_cart' => [$product->id => 2]])->post(route('customer.order.reorder', $order))
            ->assertRedirect($preview)->assertSessionHas('storefront_cart', [$product->id => 2]);
        $this->get($preview)->assertOk()->assertSee('Retired legacy item')->assertSee('Quantities adjusted')
            ->assertViewHas('reorder', fn ($draft) => $draft['quantities'] === [$product->id => 4]);
        $this->actingAs($this->customer())->post(route('customer.order.reorder', $order))->assertNotFound();
    }

    public function test_profile_approvals_cannot_overwrite_a_later_edit_and_rejections_are_recorded(): void
    {
        $buyer = $this->customer();
        $customer = $buyer->customerRecord();
        $phone = $buyer->phone ?: '5550100';
        $this->actingAs($buyer)->put(route('customer.profile.update'), ['name' => $buyer->name, 'phone' => $phone, 'city' => 'Toronto'])
            ->assertSessionHasNoErrors();
        $change = $customer->profileRequests()->sole();
        $this->put(route('customer.profile.update'), ['name' => 'Second request', 'phone' => $phone])->assertSessionHasErrors('profile');
        $customer->update(['city' => 'Markham']);
        $this->actingAs($this->admin())->patch(route('admin.profile-requests.review', $change), ['status' => 'approved'])->assertSessionHasErrors('decision');
        $this->assertSame('Markham', $customer->fresh()->city);
        $this->patch(route('admin.profile-requests.review', $change), ['status' => 'rejected', 'review_note' => 'Please confirm the new city.'])
            ->assertSessionHasNoErrors();
        $this->actingAs($buyer)->get(route('customer.profile'))->assertOk()->assertSee('Rejected')->assertSee('Please confirm the new city.');
        $this->actingAs($this->customer())->get(route('customer.profile'))->assertOk()->assertDontSee('Please confirm the new city.');
    }

    public function test_selected_invoices_consolidate_in_one_verified_helcim_receipt_and_webhook_is_idempotent(): void
    {
        $buyer = $this->customer();
        $first = $this->invoice($buyer, 10000, 'INVOICE-ONE');
        $second = $this->invoice($buyer, 15000, 'INVOICE-TWO');
        $untouched = $this->invoice($buyer, 32000, 'INVOICE-THREE');
        $otherBuyer = $this->customer();
        $other = $this->invoice($otherBuyer, 10000, 'INVOICE-OTHER');
        $this->gateway();
        Http::preventStrayRequests();
        $transaction = [];
        Http::fake(function ($request) use (&$transaction) {
            if (str_ends_with($request->url(), '/helcim-pay/initialize')) {
                $this->assertEquals(250, $request['amount']);
                $this->assertSame(0, $request['hasConvenienceFee']);
                $this->assertCount(2, $request['invoiceRequest']['lineItems']);
                $transaction = ['invoiceNumber' => $request['invoiceRequest']['invoiceNumber'], 'transactionId' => '78910',
                    'status' => 'APPROVED', 'type' => 'purchase', 'currency' => 'CAD', 'amount' => '250.00'];
                return Http::response(['checkoutToken' => 'invoice-test-123456', 'secretToken' => 'never-stored']);
            }
            return Http::response(str_contains($request->url(), '/card-transactions?') ? [$transaction] : $transaction);
        });

        $this->actingAs($buyer)->get(route('customer.invoices'))->assertOk()->assertSee('Pay Outstanding Balance')->assertSee('Pay '.$first->label);
        $data = ['invoice_ids' => [$first->id, $second->id], 'expected_total' => 25000];
        $this->post(route('customer.invoice-payments.start'), ['invoice_ids' => [$other->id], 'expected_total' => 10000])->assertNotFound();
        $this->postJson(route('customer.invoice-payments.start'), array_replace($data, ['expected_total' => 24999]))->assertUnprocessable();
        $this->post(route('customer.invoice-payments.start'), $data)->assertRedirect();
        $payment = InvoicePayment::sole();
        $this->post(route('customer.invoice-payments.start'), $data)->assertRedirect(route('customer.invoice-payments.show', $payment->reference));
        $this->assertDatabaseCount('invoice_payments', 1);
        $this->assertDatabaseCount('order_settlements', 0);
        $this->get(route('customer.invoice-payments.show', $payment->reference))->assertOk()->assertSee('Pay CAD $250.00 by card');
        $this->actingAs($otherBuyer)->get(route('customer.invoice-payments.show', $payment->reference))->assertNotFound();
        $this->actingAs($buyer)->postJson(route('customer.invoice-payments.confirm', $payment->reference), ['transaction_id' => '78910'])
            ->assertOk()->assertJson(['paid' => true]);

        $body = json_encode(['type' => 'cardTransaction', 'id' => '78910']);
        $time = (string) time();
        $signature = base64_encode(hash_hmac('sha256', 'invoice-test.'.$time.'.'.$body, 'webhook-test', true));
        $this->call('POST', '/payments/card-events?mode=live', [], [], [], ['CONTENT_TYPE' => 'application/json',
            'HTTP_WEBHOOK_ID' => 'invoice-test', 'HTTP_WEBHOOK_TIMESTAMP' => $time, 'HTTP_WEBHOOK_SIGNATURE' => 'v1,'.$signature], $body)->assertOk();

        $this->assertDatabaseCount('customer_receipts', 1);
        $this->assertDatabaseCount('order_settlements', 2);
        $this->assertSame(0, $first->order->fresh()->balance_cents);
        $this->assertSame(0, $second->order->fresh()->balance_cents);
        $this->assertSame(32000, $untouched->order->fresh()->balance_cents);
        $event = $payment->fresh()->receipt->events()->sole();
        $this->get(route('customer.documents.file', ['account-receipt', $event->id]))->assertOk()
            ->assertSee($first->label)->assertSee($second->label)->assertSee('Card (Helcim)')->assertDontSee($untouched->label);
        $this->get(route('customer.documents.file', ['account-receipt', $event->id, 'download' => 1]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame(['selected', 'ready', 'paid'], $payment->events()->pluck('action')->all());
    }

    public function test_mismatched_gateway_amount_is_recorded_for_review_without_a_paid_receipt(): void
    {
        $buyer = $this->customer();
        $invoice = $this->invoice($buyer, 25000, 'REVIEW-INVOICE');
        $this->gateway();
        Http::preventStrayRequests();
        $row = [];
        $refund = null;
        Http::fake(function ($request) use (&$row, &$refund) {
            if (str_ends_with($request->url(), '/helcim-pay/initialize')) {
                $row = ['invoiceNumber' => $request['invoiceRequest']['invoiceNumber'], 'transactionId' => '78911', 'status' => 'APPROVED',
                    'type' => 'purchase', 'currency' => 'CAD', 'amount' => '200.00'];
                return Http::response(['checkoutToken' => 'invoice-test-review']);
            }
            return Http::response(str_contains($request->url(), '/card-transactions?') ? array_values(array_filter([$row, $refund])) : $row);
        });
        $this->actingAs($buyer)->post(route('customer.invoice-payments.start'), ['invoice_ids' => [$invoice->id], 'expected_total' => 25000])->assertRedirect();
        $payment = InvoicePayment::sole();
        $this->postJson(route('customer.invoice-payments.confirm', $payment->reference), ['transaction_id' => '78911'])->assertOk()->assertJson(['paid' => false]);
        $this->assertDatabaseHas('invoice_payments', ['transaction_id' => '78911', 'status' => 'needs_review']);
        $this->assertDatabaseCount('customer_receipts', 0);
        $this->assertSame(25000, $invoice->order->fresh()->balance_cents);
        $this->get(route('admin.invoice-payments.show', $payment->reference))->assertRedirect(route('customer.orders'));
        $this->actingAs($this->admin())->get(route('admin.invoice-payments.show', $payment->reference))->assertOk()
            ->assertSee('Transaction log')->assertSee('whose currency or amount differs');
        $refund = array_replace($row, ['transactionId' => '78912', 'type' => 'refund']);
        $this->post(route('admin.invoice-payments.check', $payment->reference))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('refunded', $payment->fresh()->status);
        $this->assertDatabaseCount('customer_receipts', 0);
    }

    public function test_support_is_owned_by_the_customer_and_staff_can_reply_update_status_and_keep_private_notes(): void
    {
        $buyer = $this->customer();
        $this->actingAs($buyer)->post(route('customer.support.store'), ['subject' => 'Delivery question', 'body' => 'Can you check my delivery?'])->assertRedirect();
        $ticket = SupportTicket::sole();
        $this->assertDatabaseHas('email_history', ['type' => 'support_request', 'recipient' => config('mail.order_notification_address'), 'status' => 'queued']);
        $this->assertSame('pending', $ticket->status);
        $this->get(route('customer.support.show', $ticket))->assertOk()->assertSee('Can you check my delivery?');
        $this->patch(route('admin.support.status', $ticket), ['status' => 'resolved'])->assertRedirect(route('customer.orders'));
        $this->actingAs($this->customer())->get(route('customer.support.show', $ticket))->assertNotFound();
        $this->post(route('customer.support.reply', $ticket), ['body' => 'Unauthorized'])->assertNotFound();
        $this->actingAs($this->admin())->get(route('admin.support.index'))->assertOk()->assertSee('Delivery question');
        $this->patch(route('admin.support.status', $ticket), ['status' => 'in_progress'])->assertRedirect();
        $this->post(route('admin.support.reply', $ticket), ['body' => 'We are looking into it.'])->assertRedirect();
        $this->post(route('admin.support.reply', $ticket), ['body' => 'Internal handover', 'internal' => true])->assertRedirect();
        $this->patch(route('admin.support.status', $ticket), ['status' => 'resolved'])->assertRedirect();
        $this->actingAs($buyer)->get(route('customer.support.show', $ticket))->assertOk()->assertSee('We are looking into it.')
            ->assertSee('Resolved')->assertDontSee('Internal handover');
        $this->post(route('customer.support.reply', $ticket), ['body' => 'I still have a question.'])->assertRedirect();
        $this->assertSame('pending', $ticket->fresh()->status);
    }

    private function gateway(): void
    {
        GatewaySetting::forceCreate(['id' => 1, 'mode' => 'live', 'helcim_enabled' => true, 'credentials' => [
            'live' => ['helcim' => ['api_token' => 'test-only-token', 'webhook_secret' => base64_encode('webhook-test')]],
        ]]);
    }

    private function customer(): User
    {
        return User::factory()->create(['role_id' => Role::where('name', 'Customer')->value('id'), 'is_active' => true, 'account_type' => 'individual']);
    }

    private function admin(): User
    {
        return User::factory()->create(['role_id' => Role::where('is_super', true)->value('id'), 'is_active' => true]);
    }

    private function invoice(User $buyer, int $amount, string $number): CustomerInvoice
    {
        $order = $this->order($buyer, $amount, $number, 'delivered');
        $order->items()->create(['name' => 'Invoice item', 'quantity' => 1, 'unit_cents' => $amount, 'line_cents' => $amount]);

        return app(CustomerInvoicing::class)->issue($order);
    }

    private function order(User $buyer, int $amount, string $number, string $status): Order
    {
        return Order::create(['customer_id' => $buyer->customerRecord()->id, 'number' => $number, 'checkout_token' => Str::uuid(),
            'first_name' => 'Invoice', 'last_name' => 'Buyer', 'email' => $buyer->email, 'phone' => '123',
            'address' => '', 'city' => '', 'province' => '', 'postal_code' => '', 'country' => 'Canada', 'fulfillment' => 'pickup',
            'ordered_at' => now(), 'status' => $status, 'payment_method' => 'credit', 'payment_status' => 'unpaid',
            'subtotal_cents' => $amount, 'delivery_cents' => 0, 'tax_cents' => 0, 'currency' => 'CAD']);
    }
}
