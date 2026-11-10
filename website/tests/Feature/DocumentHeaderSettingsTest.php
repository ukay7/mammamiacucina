<?php

namespace Tests\Feature;

use App\Models\{GeneralSetting, Order, OrderSettlement, Role, User};
use App\Services\{CustomerInvoicing, DocumentTheme};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DocumentHeaderSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function login(): void
    {
        $this->actingAs(User::factory()->create(['role_id' => Role::where('is_super', true)->value('id'), 'is_active' => true]));
    }

    private function payload(array $extra = []): array
    {
        $settings = GeneralSetting::findOrFail(1);
        return array_merge(['revision' => $settings->revision, 'delivery' => number_format($settings->delivery_cents / 100, 2, '.', ''),
            'tax' => number_format($settings->tax_basis_points / 100, 2, '.', '')], $extra);
    }

    private function order(): Order
    {
        $order = Order::create(['checkout_token' => Str::uuid(), 'number' => 'MMC-HEADER-QA', 'source' => 'website',
            'first_name' => 'Header', 'last_name' => 'Customer', 'email' => 'customer@example.test', 'phone' => '123',
            'address' => 'Street', 'city' => 'Toronto', 'province' => 'ON', 'postal_code' => 'M5V', 'country' => 'Canada',
            'subtotal_cents' => 10000, 'delivery_cents' => 0, 'tax_cents' => 0, 'currency' => 'CAD', 'fulfillment' => 'pickup',
            'pickup_address' => 'Original pickup instructions', 'payment_method' => 'cash', 'payment_status' => 'unpaid',
            'status' => 'completed', 'ordered_at' => '2026-11-02 18:12:00']);
        $order->items()->create(['name' => 'Historical cake', 'product_code' => 'HEADER-1', 'qr_code' => 'HEADER-100', 'quantity' => 1, 'unit_cents' => 10000, 'line_cents' => 10000]);
        return $order->fresh();
    }

    public function test_migration_adds_approved_document_defaults_without_changing_existing_settings(): void
    {
        $migration = require database_path('migrations/2026_11_10_120000_add_document_header_settings.php');
        $migration->down();
        DB::table('general_settings')->where('id', 1)->update(['email' => 'existing@example.test', 'phone' => '416-555-0123',
            'pickup_address' => 'Existing checkout pickup', 'delivery_cents' => 1499, 'tax_basis_points' => 1300, 'revision' => 7]);
        $before = GeneralSetting::findOrFail(1)->getRawOriginal();
        $migration->up();
        $settings = GeneralSetting::findOrFail(1);
        foreach ($before as $key => $value) $this->assertSame($value, $settings->getRawOriginal($key), $key);
        $this->assertSame('Mamma Mia Cucina INC.', $settings->document_company_name);
        $this->assertSame('1 Sir Bodwin Place, Markham, ON L3P 2X7', $settings->mailing_address);
        $this->assertSame('471 Jevlan Dr, Woodbridge, ON L4L 8E3', $settings->document_warehouse_address);
        $this->assertSame('737385034RT0001', $settings->gst_hst_number);
    }

    public function test_settings_save_updates_document_details_preserves_pickup_and_rejects_stale_edits(): void
    {
        $this->login();
        GeneralSetting::findOrFail(1)->update(['pickup_address' => 'Checkout pickup remains unchanged']);
        $this->get(route('admin.settings.general'))->assertOk()->assertSee('Document Header')->assertSee('Company legal name')->assertSee('GST/HST number');
        $data = $this->payload(['document_company_name' => 'Updated Company INC.', 'mailing_address' => "Mailing street\nToronto, ON", 'document_warehouse_address' => 'Different warehouse',
            'gst_hst_number' => '123456789RT0001', 'email' => 'documents@example.test', 'phone' => '+1 (416) 555-0123']);
        $this->put(route('admin.settings.update'), $data)->assertSessionHasNoErrors()->assertRedirect(route('admin.settings.general'));
        $settings = GeneralSetting::findOrFail(1);
        $this->assertSame('Updated Company INC.', $settings->document_company_name);
        $this->assertSame('Checkout pickup remains unchanged', $settings->pickup_address);
        $brand = app(DocumentTheme::class)->brand();
        $this->assertSame($settings->mailing_address, $brand['mailing_address']);
        $this->assertSame('Different warehouse', $brand['warehouse_address']);
        $this->assertSame('123456789RT0001', $brand['tax_number']);
        $this->assertSame('documents@example.test', $brand['email']);
        $this->put(route('admin.settings.update'), $data)->assertSessionHasErrors('settings');
    }

    public function test_blank_optional_details_are_hidden_and_header_content_is_escaped(): void
    {
        $this->login();
        $this->put(route('admin.settings.update'), $this->payload(['document_company_name' => '<script>Company</script> INC.',
            'mailing_address' => '', 'document_warehouse_address' => '', 'gst_hst_number' => '', 'phone' => '', 'email' => '']))->assertSessionHasNoErrors()->assertRedirect(route('admin.settings.general'));
        $html = view('documents.header', ['doc' => ['brand' => app(DocumentTheme::class)->brand(), 'title' => 'Invoice', 'number' => 'inv-1', 'date' => '02 Nov 2026 18:12 UTC']])->render();
        $this->assertStringContainsString('&lt;script&gt;Company&lt;/script&gt; INC.', $html);
        foreach (['<script>', 'Contact Us:', 'Mailing Address:', 'Warehouse:', 'GST/HST:'] as $text) $this->assertStringNotContainsString($text, $html);
        $this->assertStringContainsString('Monday, 02 Nov 2026 13:12 EST', $html);
    }

    public function test_document_settings_require_permission_and_valid_lengths(): void
    {
        $this->login();
        foreach (['document_company_name' => str_repeat('x', 161), 'mailing_address' => str_repeat('x', 2001),
            'document_warehouse_address' => str_repeat('x', 2001), 'gst_hst_number' => str_repeat('x', 81)] as $field => $value) {
            $this->putJson(route('admin.settings.update'), $this->payload([$field => $value]))->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->putJson(route('admin.settings.update'), $this->payload(['document_company_name' => '  ']))->assertUnprocessable()->assertJsonValidationErrors('document_company_name');
        $role = Role::create(['name' => 'Read only document header', 'permissions' => ['dashboard.view']]);
        $this->actingAs(User::factory()->create(['role_id' => $role->id, 'is_active' => true]));
        $this->get(route('admin.settings.general'))->assertForbidden();
        $this->put(route('admin.settings.update'), $this->payload(['document_company_name' => 'Unauthorized']))->assertForbidden();
    }

    public function test_financial_documents_share_header_keep_disclaimers_and_do_not_rewrite_history(): void
    {
        $this->login();
        GeneralSetting::findOrFail(1)->update(['phone' => '+1 (416) 700-9969', 'email' => 'Info@mammamiacucina.ca']);
        $order = $this->order();
        $invoice = app(CustomerInvoicing::class)->issue($order);
        $invoice->update(['issued_at' => '2026-11-02 18:12:00']);
        $entry = OrderSettlement::create(['order_id' => $order->id, 'method' => 'cash', 'amount_cents' => 1000, 'note' => 'Historical payment', 'created_at' => '2026-11-02 18:12:00'])->fresh();
        $before = [$order->getRawOriginal(), $invoice->fresh()->getRawOriginal(), $entry->getRawOriginal()];
        $theme = app(DocumentTheme::class);
        foreach ([$theme->order($order), $theme->order($order, $invoice), $theme->order($order, receipt: $entry)] as $doc) {
            $html = view('documents.financial', ['doc' => $doc, 'pdf' => true])->render();
            foreach (['Mamma Mia Cucina INC.', 'Contact Us:', 'Info@mammamiacucina.ca', 'Mailing Address:', '1 Sir Bodwin Place',
                'Warehouse:', '471 Jevlan Dr', 'GST/HST: 737385034RT0001', 'Monday, 02 Nov 2026 13:12 EST',
                'Order confirmations, invoices and payment receipts are separate records; they do not create additional charges.'] as $text) $this->assertStringContainsString($text, $html);
            $this->assertStringContainsString($doc['notice'], $html);
        }
        $this->assertSame($before, [$order->fresh()->getRawOriginal(), $invoice->fresh()->getRawOriginal(), $entry->fresh()->getRawOriginal()]);
        $this->get(route('admin.orders.print', $order))->assertOk()->assertSee('Mamma Mia Cucina INC.')->assertSee('GST/HST: 737385034RT0001');
        $this->get('/admin/products/export?format=pdf')->assertOk()->assertSee('Mamma Mia Cucina INC.')->assertSee('GST/HST: 737385034RT0001');
        $this->get('/admin/orders/export?format=print&from=2026-11-01&to=2026-11-03')->assertOk()->assertSee('Mamma Mia Cucina INC.')->assertSee('GST/HST: 737385034RT0001');
        $html = view('admin.orders.packing-print', ['order' => $order])->render();
        $this->assertStringContainsString('Mamma Mia Cucina INC.', $html);
        $this->assertStringContainsString('Check quantities before dispatch.', $html);
    }
}
