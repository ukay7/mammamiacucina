<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplierOrder;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StaffPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupplierOrderWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function staff(array $permissions = ['purchases.manage', 'suppliers.manage']): User
    {
        return User::factory()->create(['role_id' => Role::create(['name' => 'Purchasing '.Str::uuid(),
            'permissions' => StaffPermissions::normalize($permissions)])->id, 'is_active' => true]);
    }

    private function product(Supplier $supplier, string $name, ?int $stock = 10, array $pricing = []): Product
    {
        $product = Product::create(['slug' => Str::uuid(), 'category_id' => Category::first()->id,
            'supplier_id' => $supplier->id, 'premium_marketing_name' => $name, 'product_code' => 'CODE-'.Str::random(6), 'qr_code' => Str::uuid(), 'is_active' => true]);
        $product->inventory()->create(['quantity_on_hand' => $stock, 'low_stock_threshold' => 5]);
        $product->pricingDraft()->create(['inputs' => $pricing + ['purchase_price' => '2', 'discount' => '38',
            'purchase_basis' => 'piece', 'pieces_per_carton' => 6, 'currency' => 'EUR'], 'options' => [], 'revision' => 1]);

        return $product;
    }

    public function test_supplier_products_sort_by_real_stock_and_show_the_approved_valuation_formula(): void
    {
        $supplier = Supplier::create(['name' => 'Purchasing supplier']);
        $high = $this->product($supplier, 'High stock', 50);
        $unset = $this->product($supplier, 'Unknown stock', null);
        $low = $this->product($supplier, 'Low stock', 1);
        $negative = $this->product($supplier, 'Shortage', -2);
        $other = $this->product(Supplier::create(['name' => 'Other supplier']), 'Not this supplier', 0);
        $response = $this->actingAs($this->staff())->get(route('admin.supplier-orders.create', ['supplier_id' => $supplier->id]))->assertOk();
        $products = $response->viewData('products');
        $this->assertSame([$negative->id, $low->id, $high->id, $unset->id], $products->modelKeys());
        $this->assertSame('7.44', $products->first()->valuation['carton_cost']);
        $this->assertSame('-14.88', $products->first()->valuation['inventory_value']);
        $response->assertDontSee($other->premium_marketing_name)->assertSee('Order quantity')->assertSee('Review Selected Products');
    }

    public function test_preview_place_complete_and_delete_preserve_inventory_sales_and_price_snapshots(): void
    {
        $supplier = Supplier::create(['name' => 'Snapshot supplier', 'email' => 'supplier@example.test']);
        $eur = $this->product($supplier, 'Piece product');
        $cad = $this->product($supplier, 'Carton product', 4, ['purchase_basis' => 'carton', 'purchase_price' => '20', 'discount' => '10', 'currency' => 'CAD']);
        $actor = $this->staff();
        $this->actingAs($actor);
        $before = $this->inventorySnapshot();
        $input = ['supplier_id' => $supplier->id, 'items' => [['product_id' => $eur->id, 'quantity' => '2'], ['product_id' => $cad->id, 'quantity' => '3']]];
        $preview = $this->postJson(route('admin.supplier-orders.preview'), $input)->assertOk()->json();
        $this->assertSame(['CAD' => '54.00', 'EUR' => '14.88'], $preview['totals']);
        $this->assertSame(0, SupplierOrder::count());
        $input += ['preview_hash' => $preview['preview_hash'], 'request_key' => (string) Str::uuid(), 'notes' => 'Bring frozen cartons'];
        $response = $this->postJson(route('admin.supplier-orders.store'), $input)->assertOk();
        $order = SupplierOrder::firstOrFail();
        $this->assertSame(route('admin.supplier-orders.show', $order), $response->json('redirect'));
        $this->assertSame('placed', $order->status);
        $this->assertSame('PO-'.(3999 + $order->id), $order->number);
        $this->postJson(route('admin.supplier-orders.store'), $input)->assertOk();
        $this->assertSame(1, SupplierOrder::count());
        $this->assertSame(2, $order->items()->count());
        $supplier->update(['name' => 'Later supplier name']);
        $eur->pricingDraft->update(['inputs' => ['purchase_price' => 500]]);
        $order->refresh();
        $this->assertSame('Snapshot supplier', $order->supplier_snapshot['name']);
        $this->assertSame('14.88', $order->items()->where('product_id', $eur->id)->first()->line_total);
        $this->post(route('admin.supplier-orders.status', $order), ['status' => 'completed', 'revision' => 0, 'confirm' => 1])->assertSessionHasNoErrors();
        $this->assertSame('completed', $order->fresh()->status);
        $this->post(route('admin.supplier-orders.status', $order), ['status' => 'placed', 'revision' => 0, 'confirm' => 1])->assertSessionHasErrors('revision');
        $this->post(route('admin.supplier-orders.status', $order), ['status' => 'deleted', 'revision' => 1, 'confirm' => 1])->assertSessionHasErrors('reason');
        $this->post(route('admin.supplier-orders.status', $order), ['status' => 'deleted', 'revision' => 1, 'confirm' => 1, 'reason' => 'Incorrect test order'])->assertSessionHasNoErrors();
        $this->assertSame('deleted', $order->fresh()->status);
        $this->get(route('admin.supplier-orders.show', $order))->assertOk()->assertSee('view only')->assertDontSee('Save Status');
        $this->post(route('admin.supplier-orders.status', $order), ['status' => 'placed', 'revision' => 2, 'confirm' => 1])->assertSessionHasErrors('status');
        $this->assertSame(3, $order->events()->count());
        $this->assertSame($before, $this->inventorySnapshot());
        $this->get(route('admin.supplier-orders.index'))->assertOk()->assertDontSee($order->number);
        $this->get(route('admin.supplier-orders.index', ['status' => 'deleted']))->assertOk()->assertSee($order->number);
    }

    public function test_stale_preview_cross_supplier_missing_prices_and_invalid_quantities_are_rejected(): void
    {
        $supplier = Supplier::create(['name' => 'Safety supplier']);
        $product = $this->product($supplier, 'Price changes');
        $other = $this->product(Supplier::create(['name' => 'Unrelated supplier']), 'Wrong supplier');
        $this->actingAs($this->staff());
        $input = ['supplier_id' => $supplier->id, 'items' => [['product_id' => $product->id, 'quantity' => '1']]];
        $preview = $this->postJson(route('admin.supplier-orders.preview'), $input)->assertOk()->json();
        $draft = $product->pricingDraft;
        $draft->update(['inputs' => [...$draft->inputs, 'purchase_price' => '3']]);
        $this->postJson(route('admin.supplier-orders.store'), $input + ['preview_hash' => $preview['preview_hash'], 'request_key' => (string) Str::uuid()])
            ->assertUnprocessable()->assertJsonValidationErrors('preview_hash');
        $this->postJson(route('admin.supplier-orders.preview'), ['supplier_id' => $supplier->id, 'items' => [['product_id' => $other->id, 'quantity' => '1']]])->assertUnprocessable();
        foreach (['0', '-1', '0.0001', '1000001', '1e2'] as $quantity) {
            $input['items'][0]['quantity'] = $quantity;
            $this->postJson(route('admin.supplier-orders.preview'), $input)->assertUnprocessable();
        }
        $draft->update(['inputs' => [...$draft->inputs, 'purchase_price' => null]]);
        $input['items'][0]['quantity'] = '1';
        $this->postJson(route('admin.supplier-orders.preview'), $input)->assertUnprocessable();
        $this->assertSame(0, SupplierOrder::count());
    }

    public function test_read_only_and_unrelated_staff_cannot_place_or_change_supplier_orders(): void
    {
        $supplier = Supplier::create(['name' => 'Permission supplier']);
        $product = $this->product($supplier, 'Permission product');
        $input = ['supplier_id' => $supplier->id, 'items' => [['product_id' => $product->id, 'quantity' => 1]]];
        $this->actingAs($this->staff(['access.admin.supplier-orders.index']));
        $this->get(route('admin.supplier-orders.index'))->assertOk()->assertDontSee('Create Supplier Order');
        $this->get(route('admin.supplier-orders.create'))->assertForbidden();
        $this->postJson(route('admin.supplier-orders.preview'), $input)->assertForbidden();
        $this->postJson(route('admin.supplier-orders.store'), $input)->assertForbidden();
        $this->actingAs($this->staff(['access.admin.products.index']))->get(route('admin.suppliers.index'))->assertForbidden();
        $this->get(route('admin.supplier-orders.index'))->assertForbidden();
    }

    public function test_warehouse_stock_is_used_and_changes_during_review_do_not_change_purchase_prices(): void
    {
        $supplier = Supplier::create(['name' => 'Warehouse supplier']);
        $first = $this->product($supplier, 'Global low', 1);
        $second = $this->product($supplier, 'Location low', 20);
        $warehouse = Warehouse::where('is_active', true)->firstOrFail();
        $first->warehouseInventories()->updateOrCreate(['warehouse_id' => $warehouse->id], ['quantity_on_hand' => 15]);
        $second->warehouseInventories()->updateOrCreate(['warehouse_id' => $warehouse->id], ['quantity_on_hand' => 2]);
        $this->actingAs($this->staff());
        $products = $this->get(route('admin.supplier-orders.create', ['supplier_id' => $supplier->id, 'warehouse_id' => $warehouse->id]))->assertOk()->viewData('products');
        $this->assertSame([$second->id, $first->id], $products->modelKeys());
        $this->assertSame('14.88', $products->first()->valuation['inventory_value']);
        $input = ['supplier_id' => $supplier->id, 'warehouse_id' => $warehouse->id, 'items' => [['product_id' => $second->id, 'quantity' => '2']]];
        $preview = $this->postJson(route('admin.supplier-orders.preview'), $input)->assertOk()->json();
        $second->warehouseInventories()->where('warehouse_id', $warehouse->id)->update(['quantity_on_hand' => 1]);
        $before = $this->inventorySnapshot();
        $this->postJson(route('admin.supplier-orders.store'), $input + ['preview_hash' => $preview['preview_hash'], 'request_key' => (string) Str::uuid()])->assertOk();
        $this->assertSame(['EUR' => '14.88'], SupplierOrder::firstOrFail()->totals);
        $this->assertSame($before, $this->inventorySnapshot());
    }

    public function test_changed_supplier_contacts_require_review_again_and_inactive_suppliers_cannot_receive_orders(): void
    {
        $supplier = Supplier::create(['name' => 'Reviewed supplier']);
        $product = $this->product($supplier, 'Reviewed product');
        $this->actingAs($this->staff());
        $input = ['supplier_id' => $supplier->id, 'items' => [['product_id' => $product->id, 'quantity' => '1']]];
        $preview = $this->postJson(route('admin.supplier-orders.preview'), $input)->assertOk()->json();
        $supplier->update(['email' => 'new-contact@example.test']);
        $this->postJson(route('admin.supplier-orders.store'), $input + ['preview_hash' => $preview['preview_hash'], 'request_key' => (string) Str::uuid()])
            ->assertUnprocessable()->assertJsonValidationErrors('preview_hash');
        $supplier->update(['is_active' => false]);
        $this->postJson(route('admin.supplier-orders.preview'), $input)->assertUnprocessable()->assertJsonValidationErrors('supplier_id');
        $this->assertSame(0, SupplierOrder::count());
    }

    private function inventorySnapshot(): array
    {
        return [DB::table('inventories')->get()->map(fn ($row) => (array) $row)->all(), DB::table('warehouse_inventories')->get()->map(fn ($row) => (array) $row)->all(),
            DB::table('inventory_movements')->count(), DB::table('orders')->count(), DB::table('customer_invoices')->count()];
    }
}
