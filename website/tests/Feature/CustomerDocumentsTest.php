<?php

namespace Tests\Feature;

use App\Models\{Customer, CustomerReceiptEvent, EmailHistory, Order, Quotation, Role, User};
use App\Services\{CustomerDocuments, CustomerInvoicing, CustomerReceipts};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_active' => true, 'role_id' => Role::where('is_super', true)->value('id')]);
    }

    private function customer(): Customer
    {
        return User::factory()->create(['name' => 'Document Buyer', 'account_type' => 'individual', 'role_id' => Role::where('name', 'Customer')->value('id'), 'is_active' => true, 'email_verified_at' => now()])->customerRecord();
    }

    private function order(Customer $customer, array $extra = []): Order
    {
        $order = Order::create($extra + ['customer_id' => $customer->id, 'checkout_token' => Str::uuid(), 'number' => 'DOC-'.Str::random(7),
            'first_name' => 'Document', 'last_name' => 'Buyer', 'email' => $customer->email, 'phone' => '555123',
            'address' => 'Test Street', 'city' => 'Toronto', 'province' => 'ON', 'postal_code' => 'M5V', 'country' => 'Canada',
            'subtotal_cents' => 10000, 'delivery_cents' => 0, 'tax_cents' => 0, 'payment_method' => 'cash', 'payment_status' => 'unpaid',
            'manual_collected_cents' => 1000, 'status' => 'delivered', 'fulfillment' => 'pickup', 'source' => 'website']);
        $order->items()->create(['name' => 'Original invoice cake', 'product_code' => 'CAKE', 'qr_code' => 'CAKE', 'quantity' => 1, 'unit_cents' => 10000, 'line_cents' => 10000]);
        return $order->fresh();
    }

    private function fixture(): array
    {
        $admin = $this->admin(); $customer = $this->customer(); $order = $this->order($customer);
        $invoice = app(CustomerInvoicing::class)->issue($order);
        $proof = 'transfer-receipts/'.$order->id.'.pdf'; Storage::disk('local')->put($proof, '%PDF-1.4 test proof');
        $order->update(['transfer_receipt_path' => $proof, 'transfer_receipt_uploaded_at' => now()]);
        $settlement = $order->settlements()->create(['method' => 'cash', 'amount_cents' => 500, 'note' => 'Verified collection', 'receipt_path' => $proof]);
        $service = app(CustomerReceipts::class);
        $input = ['lines' => [['key' => (string) Str::uuid(), 'method' => 'cash', 'amount' => '10', 'payment_date' => today()->toDateString(), 'verified' => true]]];
        $preview = $service->preview($customer, $input, $admin);
        $receipt = $service->create($customer, $input + ['preview_hash' => $preview['preview_hash'], 'request_key' => (string) Str::uuid(), 'confirmed' => 1], $admin);
        $line = $receipt->lines()->first(); $line->update(['proof_path' => $proof, 'proof_name' => 'Bank <review>.pdf']);
        $event = $receipt->events()->first();
        $quotation = Quotation::create(['customer_id' => $customer->id, 'created_by' => $admin->id, 'due_date' => now()->addDays(10), 'pricing_tier' => 'individual',
            'customer_snapshot' => $customer->only(['name','business_name','email','phone','address','city','province','postal_code','country']),
            'items' => [['code' => 'CAKE', 'name' => 'Quoted cake', 'quantity' => 1, 'unit_cents' => 1000, 'discount_basis_points' => 0, 'pieces_per_carton' => 1]],
            'subtotal_cents' => 1000, 'line_discount_cents' => 0, 'discount_cents' => 0, 'charge_cents' => 0, 'charge_basis_points' => 0, 'tax_cents' => 0, 'tax_basis_points' => 0, 'total_cents' => 1000]);
        $email = EmailHistory::create(['order_id' => $order->id, 'recipient' => $customer->email, 'type' => 'order_confirmation', 'subject' => 'Order copy', 'status' => 'accepted',
            'archived_at' => now(), 'sent_at' => now(), 'archive' => ['body' => 'PRIVATE MAIL BODY', 'attachment' => base64_encode('%PDF-1.4 exact email bytes')]]);
        $ids = ['order' => $order->id, 'invoice' => $invoice->id, 'initial-payment' => $order->id, 'settlement' => $settlement->id,
            'transfer-proof' => $order->id, 'settlement-proof' => $settlement->id, 'account-receipt' => $event->id, 'account-proof' => $line->id,
            'quotation' => $quotation->id, 'email-copy' => $email->id];
        return compact('admin','customer','order','invoice','settlement','receipt','line','event','quotation','email','ids');
    }

    protected function setUp(): void
    {
        parent::setUp(); Storage::fake('local');
    }

    public function test_customer_and_both_admin_screens_collect_existing_documents_without_writes(): void
    {
        $f = $this->fixture(); $other = $this->order($this->customer());
        $before = [DB::table('email_history')->count(), DB::table('orders')->count(), $f['invoice']->fresh()->getRawOriginal(), $f['event']->fresh()->getRawOriginal()];
        $this->actingAs($f['customer']->user);
        foreach (['customer.documents', 'customer.profile'] as $route) {
            $response = $this->get(route($route))->assertOk()->assertSee('Documents')->assertSee($f['invoice']->label)->assertSee($f['receipt']->number)
                ->assertSee('Bank &lt;review&gt;.pdf', false)->assertDontSee($other->number)->assertDontSee('PRIVATE MAIL BODY');
            $kinds = $response->viewData('documents')->getCollection()->pluck('kind')->all();
            foreach (array_keys($f['ids']) as $kind) $this->assertContains($kind, $kinds);
        }
        $this->actingAs($f['admin']);
        foreach (['admin.customers.show','admin.customers.edit'] as $route) $this->get(route($route, $f['customer']))->assertOk()->assertSee('customer-documents')->assertSee($f['invoice']->label);
        $this->assertSame($before, [DB::table('email_history')->count(), DB::table('orders')->count(), $f['invoice']->fresh()->getRawOriginal(), $f['event']->fresh()->getRawOriginal()]);
    }

    public function test_every_source_checks_customer_ownership_for_view_and_download(): void
    {
        $f = $this->fixture(); $other = $this->customer();
        $this->actingAs($other->user);
        foreach ($f['ids'] as $kind => $id) foreach ([0, 1] as $download) {
            $this->get(route('customer.documents.file', ['kind' => $kind, 'document' => $id, 'download' => $download]))->assertNotFound();
        }
        $this->actingAs($f['admin']);
        foreach ($f['ids'] as $kind => $id) $this->get(route('admin.customers.documents.file', ['customer' => $other, 'kind' => $kind, 'document' => $id]))->assertNotFound();
        $this->get(route('admin.customers.documents.file', ['customer' => $f['customer'], 'kind' => 'unknown', 'document' => 1]))->assertNotFound();
    }

    public function test_generated_files_use_saved_snapshots_and_download_real_pdfs(): void
    {
        $f = $this->fixture(); $this->actingAs($f['customer']->user);
        $f['order']->items()->update(['name' => 'Changed live product']);
        $snapshot = $f['invoice']->fresh()->getRawOriginal();
        foreach (['order','invoice','initial-payment','settlement','account-receipt','quotation'] as $kind) {
            $url = route('customer.documents.file', ['kind' => $kind, 'document' => $f['ids'][$kind]]);
            $html = $this->get($url)->assertOk()->assertSee('GST/HST:')->assertSee('Download PDF')->assertDontSee('/admin/customers/'.$f['customer']->id.'/receipts', false);
            if ($kind === 'invoice') $html->assertSee('Original invoice cake')->assertDontSee('Changed live product');
            $pdf = $this->get($url.'?download=1')->assertOk()->assertHeader('Content-Type', 'application/pdf');
            $this->assertStringStartsWith('%PDF-', $pdf->getContent());
            $this->assertStringContainsString('attachment;', $pdf->headers->get('Content-Disposition'));
            $this->assertStringContainsString('no-store', $pdf->headers->get('Cache-Control'));
        }
        $this->assertSame($snapshot, $f['invoice']->fresh()->getRawOriginal());
    }

    public function test_uploaded_files_and_saved_email_pdfs_keep_their_bytes_and_missing_files_are_hidden(): void
    {
        $f = $this->fixture(); $this->actingAs($f['customer']->user);
        foreach (['transfer-proof','settlement-proof','account-proof'] as $kind) {
            $url = route('customer.documents.file', ['kind' => $kind, 'document' => $f['ids'][$kind]]);
            foreach ([0,1] as $download) {
                $response = $this->get($url.'?download='.$download)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
                $this->assertSame('%PDF-1.4 test proof', file_get_contents($response->baseResponse->getFile()->getPathname()));
                $this->assertStringContainsString($download ? 'attachment;' : 'inline;', $response->headers->get('Content-Disposition'));
            }
        }
        $this->get(route('customer.documents.file', ['kind' => 'email-copy', 'document' => $f['email']->id]))->assertOk()->assertContent('%PDF-1.4 exact email bytes');
        $f['email']->update(['type' => 'new_order_notification']);
        $this->get(route('customer.documents.file', ['kind' => 'email-copy', 'document' => $f['email']->id]))->assertNotFound();
        Storage::disk('local')->delete($f['order']->transfer_receipt_path);
        $this->get(route('customer.documents', ['document_type' => 'uploads']))->assertOk()->assertSee('No matching documents.');
        $this->get(route('customer.documents.file', ['kind' => 'transfer-proof', 'document' => $f['order']->id]))->assertNotFound();
    }

    public function test_staff_permissions_and_customer_login_gates_are_preserved(): void
    {
        $f = $this->fixture();
        $role = Role::create(['name' => 'Document viewer', 'permissions' => ['orders.view','customers.manage']]);
        $staff = User::factory()->create(['role_id' => $role->id, 'is_active' => true]); $this->actingAs($staff);
        $this->get(route('admin.customers.show', $f['customer']))->assertOk()->assertDontSee('Bank &lt;review&gt;.pdf', false)
            ->assertViewHas('documents', fn ($documents) => ! $documents->pluck('kind')->contains('email-copy'));
        foreach (['account-receipt','account-proof','quotation','email-copy'] as $kind) $this->get(route('admin.customers.documents.file', ['customer' => $f['customer'], 'kind' => $kind, 'document' => $f['ids'][$kind]]))->assertForbidden();
        $this->get(route('admin.customers.documents.file', ['customer' => $f['customer'], 'kind' => 'invoice', 'document' => $f['invoice']->id]))->assertOk();
        $this->get(route('customer.documents'))->assertForbidden();
        $f['customer']->user->forceFill(['email_verified_at' => null])->save(); $this->actingAs($f['customer']->user->fresh());
        $this->get(route('customer.documents'))->assertRedirect(route('customer.verify.notice'));
        $f['customer']->user->update(['is_active' => false]); $this->actingAs($f['customer']->user->fresh());
        $this->get(route('customer.documents'))->assertForbidden();
        auth()->logout(); $this->get(route('customer.documents'))->assertRedirect(route('customer.login'));
    }

    public function test_restricted_staff_cannot_bypass_order_scope_through_documents(): void
    {
        $f = $this->fixture();
        $role = Role::create(['name' => 'Restricted document viewer', 'permissions' => ['orders.view', 'orders.scope.restricted']]);
        $staff = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $this->actingAs($staff);
        $f['order']->update(['status' => 'parked_admin', 'created_by' => $f['admin']->id]);
        $this->get(route('admin.customers.show', $f['customer']))->assertOk()
            ->assertViewHas('documents', fn ($documents) => $documents->total() === 0);
        foreach (['order', 'invoice', 'initial-payment', 'settlement', 'transfer-proof', 'settlement-proof'] as $kind) {
            $this->get(route('admin.customers.documents.file', ['customer' => $f['customer'], 'kind' => $kind, 'document' => $f['ids'][$kind]]))->assertNotFound();
        }
        $f['order']->update(['status' => 'warehouse_pending']);
        $this->get(route('admin.customers.documents.file', ['customer' => $f['customer'], 'kind' => 'order', 'document' => $f['order']->id]))->assertOk();
    }

    public function test_search_type_filter_pagination_new_records_and_deleted_order_boundary(): void
    {
        $customer = $this->customer(); $this->actingAs($customer->user);
        for ($i=0; $i<23; $i++) $this->order($customer, ['number' => 'SEARCH-'.str_pad($i,3,'0',STR_PAD_LEFT), 'manual_collected_cents' => 0]);
        $first = $this->get(route('customer.documents',['document_type'=>'orders']))->assertOk()->viewData('documents');
        $this->assertSame(23,$first->total()); $this->assertCount(20,$first);
        $second = $this->get(route('customer.documents',['document_type'=>'orders','documents_page'=>2]))->assertOk()->viewData('documents');
        $this->assertCount(3,$second); $this->assertSame([],array_intersect($first->pluck('id')->all(),$second->pluck('id')->all()));
        $order = $this->order($customer,['number'=>'NEW-DOCUMENT','manual_collected_cents'=>0]);
        $this->get(route('customer.documents',['document_search'=>'NEW-DOCUMENT']))->assertOk()->assertViewHas('documents',fn($p)=>$p->total()===1);
        $invoice = app(CustomerInvoicing::class)->issue($order); $order->delete();
        $this->get(route('customer.documents',['document_search'=>'NEW-DOCUMENT']))->assertOk()->assertViewHas('documents',fn($p)=>$p->total()===0);
        $this->get(route('customer.documents.file',['kind'=>'invoice','document'=>$invoice->id]))->assertNotFound();
        $this->get(route('customer.documents',['document_type'=>'private']))->assertSessionHasErrors('document_type');
    }

    public function test_receipt_versions_remain_separate_and_corrections_do_not_replace_original(): void
    {
        $f=$this->fixture(); $this->actingAs($f['customer']->user);
        $snapshot=$f['event']->snapshot;
        $later=CustomerReceiptEvent::create(['customer_receipt_id'=>$f['receipt']->id,'actor_name'=>'Reviewer','action'=>'reverse','reason'=>'Correction record','snapshot'=>$snapshot]);
        $this->get(route('customer.documents',['document_type'=>'receipts']))->assertOk()->assertSee('version #'.$f['event']->id)->assertSee('version #'.$later->id);
        $this->get(route('customer.documents.file',['kind'=>'account-receipt','document'=>$f['event']->id]))->assertOk()->assertSee('Customer Payment Receipt')->assertDontSee('Correction record');
        $this->get(route('customer.documents.file',['kind'=>'account-receipt','document'=>$later->id]))->assertOk()->assertSee('Payment Adjustment Record')->assertSee('Correction record');
        $this->assertSame($snapshot,$f['event']->fresh()->snapshot);
    }
}
