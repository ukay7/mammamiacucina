<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplierOrder;
use App\Models\User;
use App\Services\DocumentTheme;
use App\Services\StaffPermissions;
use App\Services\SupplierOrderDocument;
use App\Services\SupplierPurchasing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupplierOrderDocumentTest extends TestCase
{
    use RefreshDatabase;

    private function staff(array $permissions = ['access.admin.supplier-orders.index']): User
    {
        return User::factory()->create(['role_id' => Role::create(['name' => 'PDF '.Str::uuid(),
            'permissions' => StaffPermissions::normalize($permissions)])->id, 'is_active' => true]);
    }

    private function order(): SupplierOrder
    {
        $supplier = Supplier::create(['name' => 'Saved PDF supplier', 'email' => 'purchase@example.test']);
        $product = Product::create(['slug' => Str::uuid(), 'category_id' => Category::first()->id,
            'supplier_id' => $supplier->id, 'premium_marketing_name' => 'Saved cake', 'product_code' => 'SUP-001',
            'qr_code' => '100001', 'is_active' => true, 'original_description' => 'Original cake',
            'dna' => ['manufacturer' => 'Saved manufacturer', 'allocated_to' => 'Saved brand']]);
        $product->inventory()->create(['quantity_on_hand' => 10, 'low_stock_threshold' => 5]);
        $product->pricingDraft()->create(['inputs' => ['purchase_price' => '2', 'discount' => '38',
            'purchase_basis' => 'piece', 'pieces_per_carton' => 6, 'currency' => 'EUR'], 'options' => [], 'revision' => 1]);
        $input = ['supplier_id' => $supplier->id, 'items' => [['product_id' => $product->id, 'quantity' => '2']], 'notes' => 'Keep frozen'];
        $purchasing = app(SupplierPurchasing::class);
        $preview = $purchasing->preview($input);

        return $purchasing->create($input + ['request_key' => (string) Str::uuid(), 'preview_hash' => $preview['preview_hash']], $this->staff()->id);
    }

    public function test_preview_and_pdf_preserve_order_snapshots_after_products_and_suppliers_change(): void
    {
        $order = $this->order();
        $item = $order->items()->first();
        $item->product->update(['premium_marketing_name' => 'Later name', 'product_code' => 'NEW-CODE',
            'dna' => ['manufacturer' => 'Later manufacturer', 'allocated_to' => 'Later brand']]);
        $item->product->pricingDraft->update(['inputs' => ['purchase_price' => 999]]);
        $order->supplierRecord->update(['name' => 'Later supplier']);
        $before = [DB::table('inventories')->get()->map(fn ($row) => (array) $row)->all(), DB::table('inventory_movements')->count(), $order->fresh()->getAttributes(), $order->events()->count()];
        $this->actingAs($this->staff());
        $response = $this->getJson(route('admin.supplier-orders.pdf-preview', $order))->assertOk()
            ->assertJsonPath('supplier', 'Saved PDF supplier')->assertJsonPath('rows.0.name', 'Saved cake')
            ->assertJsonPath('rows.0.product_code', 'SUP-001')->assertJsonPath('rows.0.manufacturer', 'Saved manufacturer')
            ->assertJsonPath('rows.0.quantity', '2')->assertJsonPath('rows.0.unit_cost', 'EUR 7.44')
            ->assertJsonPath('rows.0.line_total', 'EUR 14.88')->assertJsonPath('legacy_details', false);
        $this->assertSame(SupplierOrderDocument::DEFAULTS, $response->json('defaults'));
        $pdf = $this->get(route('admin.supplier-orders.pdf', [$order, 'columns' => 'serial,name,quantity,unit_cost,line_total']))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->assertSame($before, [DB::table('inventories')->get()->map(fn ($row) => (array) $row)->all(), DB::table('inventory_movements')->count(), $order->fresh()->getAttributes(), $order->events()->count()]);
    }

    public function test_selected_columns_are_ordered_and_hidden_prices_are_not_printed(): void
    {
        $order = $this->order();
        $document = app(SupplierOrderDocument::class);
        $selected = $document->selected(Request::create('/', 'GET', ['columns' => 'name,serial,manufacturer']));
        $this->assertSame(['name', 'serial', 'manufacturer'], $selected);
        $rows = $document->rows($order);
        $html = view('documents.supplier-order', ['order' => $order, 'selected' => $selected, 'rows' => $rows,
            'labels' => $document->labels(), 'groups' => $document->groups($selected), 'notes' => $document->notes($order, $selected),
            'landscape' => false, 'doc' => ['brand' => app(DocumentTheme::class)->brand(), 'title' => 'Supplier Order',
                'number' => $order->number, 'date' => '30 Oct 2026 10:30 UTC']])->render();
        $this->assertLessThan(strpos($html, '>Serial number</th>'), strpos($html, '>Product name</th>'));
        $this->assertStringContainsString('Saved manufacturer', $html);
        $this->assertStringContainsString('Friday, 30 Oct 2026', $html);
        $this->assertStringNotContainsString('EUR 14.88', $html);
        $this->assertStringNotContainsString('>Cost per carton</th>', $html);
        $this->assertStringNotContainsString('>Line total</th>', $html);

        $keys = array_keys($document->labels());
        $groups = $document->groups($keys);
        $this->assertCount(count($keys), array_unique(array_merge(...$groups)));
        foreach ($groups as $group) {
            $this->assertLessThanOrEqual(8, count($group));
            $this->assertEmpty(array_diff($group, $keys));
        }
        $this->actingAs($this->staff())->get(route('admin.supplier-orders.pdf', [$order, 'columns' => implode(',', $keys)]))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_empty_invalid_duplicate_and_array_columns_are_rejected_before_rendering(): void
    {
        $order = $this->order();
        $this->actingAs($this->staff());
        foreach (['', 'name,password', 'name,name', ['name']] as $columns) {
            $this->getJson(route('admin.supplier-orders.pdf', [$order, 'columns' => $columns]))
                ->assertUnprocessable()->assertJsonValidationErrors('columns');
        }
        $this->assertSame(SupplierOrderDocument::DEFAULTS, app(SupplierOrderDocument::class)->selected(Request::create('/')));
    }

    public function test_legacy_details_are_labelled_deleted_orders_remain_historical_and_permissions_apply(): void
    {
        $order = $this->order();
        $item = $order->items()->first();
        $snapshot = $item->pricing_snapshot;
        unset($snapshot['product_details']);
        $item->update(['pricing_snapshot' => $snapshot]);
        $order->update(['status' => 'deleted', 'deleted_at' => now(), 'deletion_reason' => 'Historical test']);
        $this->actingAs($this->staff());
        $this->getJson(route('admin.supplier-orders.pdf-preview', $order))->assertOk()
            ->assertJsonPath('legacy_details', true)->assertJsonPath('status', 'Deleted')->assertJsonPath('rows.0.manufacturer', 'Saved manufacturer');
        $document = app(SupplierOrderDocument::class);
        $order->load('items');
        $this->assertStringContainsString('current product details', implode(' ', $document->notes($order, ['manufacturer'])));
        $this->actingAs($this->staff(['access.admin.products.index']));
        $this->getJson(route('admin.supplier-orders.pdf-preview', $order))->assertForbidden();
        $this->get(route('admin.supplier-orders.pdf', $order))->assertForbidden();
    }
}
