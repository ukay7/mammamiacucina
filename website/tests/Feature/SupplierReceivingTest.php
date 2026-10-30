<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplierOrder;
use App\Models\SupplierOrderReceipt;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Services\InventoryProducts;
use App\Services\StaffPermissions;
use App\Services\SupplierPurchasing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupplierReceivingTest extends TestCase
{
    use RefreshDatabase;

    private function actor(array $permissions = ['purchases.manage', 'inventory.manage']): User
    {
        return User::factory()->create(['is_active' => true, 'role_id' => Role::create(['name' => 'Receiving '.Str::uuid(), 'permissions' => StaffPermissions::normalize($permissions)])->id]);
    }

    private function product(?string $stock = '10'): Product
    {
        $supplier = Supplier::firstOrCreate(['name' => 'Receiving supplier']);
        $product = Product::create(['category_id' => Category::first()->id, 'slug' => Str::uuid(), 'qr_code' => Str::uuid(),
            'premium_marketing_name' => 'Receiving '.Str::random(8), 'supplier_id' => $supplier->id, 'is_active' => true]);
        if ($stock !== null) {
            $product->inventory()->create(['quantity_on_hand' => $stock, 'low_stock_threshold' => 4]);
        }
        $product->pricingDraft()->create(['inputs' => ['purchase_price' => '2', 'discount' => '38', 'purchase_basis' => 'piece', 'pieces_per_carton' => 6, 'currency' => 'EUR'], 'options' => [], 'revision' => 1]);

        return $product;
    }

    private function order(array $products, ?Warehouse $warehouse = null): SupplierOrder
    {
        $first = Product::findOrFail(array_key_first($products));
        $input = ['supplier_id' => $first->supplier_id, 'warehouse_id' => $warehouse?->id,
            'items' => collect($products)->map(fn ($quantity, $id) => ['product_id' => $id, 'quantity' => $quantity])->values()->all()];
        $service = app(SupplierPurchasing::class);
        $preview = $service->preview($input);

        return $service->create($input + ['preview_hash' => $preview['preview_hash'], 'request_key' => (string) Str::uuid()], auth()->id());
    }

    private function input(?Warehouse $warehouse, array $extra = []): array
    {
        return $extra + ['revision' => 0, 'request_key' => (string) Str::uuid(), 'add_to_inventory' => 1,
            'warehouse_id' => $warehouse?->id, 'reason' => 'Goods checked and received', 'confirm' => 1];
    }

    private function stock(): array
    {
        return collect(['inventories', 'warehouse_inventories', 'inventory_movements', 'orders', 'customer_invoices'])
            ->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all()])->all();
    }

    public function test_receiving_uses_explicit_destination_not_the_saved_filter_or_default_and_keeps_snapshots(): void
    {
        $actor = $this->actor();
        $this->actingAs($actor);
        $product = $this->product();
        $default = Warehouse::where('is_default', true)->firstOrFail();
        $destination = Warehouse::create(['name' => 'Receiving freezer', 'is_active' => true]);
        WarehouseInventory::create(['warehouse_id' => $destination->id, 'product_id' => $product->id, 'quantity_on_hand' => '3']);
        Inventory::withoutEvents(fn () => $product->inventory->update(['quantity_on_hand' => '13']));
        $order = $this->order([$product->id => '2.125'], $default);
        $snapshot = $order->items()->first()->pricing_snapshot;
        $this->post(route('admin.supplier-orders.receive', $order), $this->input($destination))->assertSessionHasNoErrors()->assertRedirect(route('admin.supplier-orders.show', $order));
        $this->assertSame('received', $order->fresh()->status);
        $this->assertSame($default->id, $order->fresh()->warehouse_id);
        $this->assertSame('10.000', WarehouseInventory::where('product_id', $product->id)->where('warehouse_id', $default->id)->first()->quantity_on_hand);
        $this->assertSame('5.125', WarehouseInventory::where('product_id', $product->id)->where('warehouse_id', $destination->id)->first()->quantity_on_hand);
        $this->assertSame('15.125', $product->inventory->fresh()->quantity_on_hand);
        $this->assertSame('4.000', $product->inventory->fresh()->low_stock_threshold);
        $receipt = $order->receipt()->firstOrFail();
        $entry = $receipt->items()->firstOrFail();
        $this->assertSame($destination->id, $receipt->warehouse_id);
        $this->assertSame(['3.000', '5.125', '13.000', '15.125'], [$entry->quantity_before, $entry->quantity_after, $entry->total_before, $entry->total_after]);
        $this->assertSame($snapshot, $order->items()->first()->pricing_snapshot);
        $movement = InventoryMovement::findOrFail($entry->inventory_movement_id);
        $this->assertSame($destination->id, $movement->warehouse_id);
        $this->assertSame($actor->id, $movement->created_by);
        $this->assertStringContainsString($order->number, $movement->reason);
        $summary = app(InventoryProducts::class)->stockSummary(app(InventoryProducts::class)->query([], $destination->id));
        $this->assertSame('5.125', $summary['quantity']);
        $actor->update(['name' => 'Renamed staff']);
        $destination->update(['name' => 'Renamed warehouse']);
        $this->get(route('admin.supplier-orders.show', $order))->assertOk()->assertSee('Receiving freezer')->assertSee($receipt->received_by_name)->assertSee('Receiving record')->assertDontSee('Mark Received');
        $this->assertSame(0, DB::table('orders')->count());
        $this->assertSame(0, DB::table('customer_invoices')->count());
    }

    public function test_no_records_the_receipt_without_touching_any_inventory_or_creating_movements(): void
    {
        $this->actingAs($this->actor(['purchases.manage']));
        $product = $this->product();
        $order = $this->order([$product->id => '5']);
        $before = $this->stock();
        $this->post(route('admin.supplier-orders.receive', $order), $this->input(null, ['add_to_inventory' => 0, 'reason' => 'Already entered stock manually']))->assertSessionHasNoErrors();
        $this->assertSame($before, $this->stock());
        $receipt = $order->receipt()->firstOrFail();
        $this->assertFalse($receipt->add_to_inventory);
        $this->assertNull($receipt->warehouse_id);
        $this->assertNull($receipt->items()->first()->inventory_movement_id);
        $this->get(route('admin.supplier-orders.show', $order))->assertOk()->assertSee('Inventory not added')->assertSee('Already entered stock manually');
    }

    public function test_unset_stock_needs_explicit_initialization_and_fractional_receipts_preserve_other_locations_and_shortages(): void
    {
        $this->actingAs($this->actor());
        $product = $this->product('-2');
        $new = $this->product(null);
        $default = Warehouse::where('is_default', true)->firstOrFail();
        $destination = Warehouse::create(['name' => 'New receiving location', 'is_active' => true]);
        $order = $this->order([$product->id => '1.5', $new->id => '0.125']);
        $input = $this->input($destination);
        $before = $this->stock();
        $this->post(route('admin.supplier-orders.receive', $order), $input)->assertSessionHasErrors('initialize_unset');
        $this->assertSame($before, $this->stock());
        $this->assertSame(0, SupplierOrderReceipt::count());
        $this->post(route('admin.supplier-orders.receive', $order), $input + ['initialize_unset' => 1])->assertSessionHasNoErrors();
        $this->assertSame('-0.500', $product->inventory->fresh()->quantity_on_hand);
        $this->assertSame('0.125', $new->fresh()->inventory->quantity_on_hand);
        $this->assertSame('-2.000', WarehouseInventory::where('product_id', $product->id)->where('warehouse_id', $default->id)->first()->quantity_on_hand);
        $this->assertFalse(WarehouseInventory::where('product_id', $new->id)->where('warehouse_id', $default->id)->exists());
        $this->assertSame(2, InventoryMovement::count());
    }

    public function test_retries_cannot_add_stock_twice_and_received_orders_cannot_be_reopened_or_deleted(): void
    {
        $this->actingAs($this->actor());
        $product = $this->product();
        $order = $this->order([$product->id => '2']);
        $input = $this->input(Warehouse::where('is_default', true)->first());
        $url = route('admin.supplier-orders.receive', $order);
        $this->post($url, $input)->assertSessionHasNoErrors();
        $this->post($url, $input)->assertSessionHasNoErrors();
        $this->assertSame(1, SupplierOrderReceipt::count());
        $this->assertSame(1, InventoryMovement::count());
        $this->assertSame('12.000', $product->inventory->fresh()->quantity_on_hand);
        $this->post($url, array_replace($input, ['request_key' => (string) Str::uuid(), 'revision' => 1]))->assertSessionHasErrors('receipt');
        foreach (['placed', 'deleted'] as $status) {
            $this->post(route('admin.supplier-orders.status', $order), ['status' => $status, 'revision' => 1, 'reason' => 'Change receipt', 'confirm' => 1])->assertSessionHasErrors('status');
        }
        $this->assertSame(2, $order->events()->count());
        $this->assertSame('12.000', $product->inventory->fresh()->quantity_on_hand);
    }

    public function test_choice_warehouse_permission_reason_confirmation_and_revision_are_enforced(): void
    {
        $actor = $this->actor();
        $this->actingAs($actor);
        $product = $this->product();
        $order = $this->order([$product->id => '2']);
        $warehouse = Warehouse::where('is_default', true)->first();
        $input = $this->input($warehouse);
        $url = route('admin.supplier-orders.receive', $order);
        foreach (['add_to_inventory', 'warehouse_id', 'reason', 'confirm'] as $field) {
            $bad = $input;
            unset($bad[$field]);
            $this->post($url, $bad)->assertSessionHasErrors($field);
        }
        $this->post($url, array_replace($input, ['reason' => '   ']))->assertSessionHasErrors('reason');
        $this->post($url, array_replace($input, ['revision' => 9]))->assertSessionHasErrors('revision');
        $warehouse->update(['is_active' => false]);
        $this->post($url, $input)->assertSessionHasErrors('warehouse_id');
        $warehouse->update(['is_active' => true]);
        $this->actingAs($this->actor(['purchases.manage']))->post($url, $input)->assertForbidden();
        $this->actingAs($this->actor(['access.admin.supplier-orders.index']))->post($url, $input)->assertForbidden();
        $this->actingAs($actor)->post(route('admin.supplier-orders.status', $order), ['status' => 'received', 'revision' => 0, 'confirm' => 1])->assertSessionHasErrors('status');
        $this->assertSame(0, InventoryMovement::count());
        $this->assertSame(0, SupplierOrderReceipt::count());
    }

    public function test_late_overflow_or_inconsistent_stock_rolls_back_the_entire_receipt(): void
    {
        $this->actingAs($this->actor());
        $first = $this->product();
        $second = $this->product('999999999');
        $order = $this->order([$first->id => '2', $second->id => '1']);
        $warehouse = Warehouse::where('is_default', true)->first();
        $input = $this->input($warehouse);
        $before = $this->stock();
        $this->post(route('admin.supplier-orders.receive', $order), $input)->assertSessionHasErrors('receipt');
        $this->assertSame($before, $this->stock());
        $this->assertSame(0, SupplierOrderReceipt::count());
        $this->assertSame('placed', $order->fresh()->status);
        Inventory::withoutEvents(fn () => $second->inventory->update(['quantity_on_hand' => '5']));
        $this->post(route('admin.supplier-orders.receive', $order), $input)->assertSessionHasErrors('receipt');
        $this->assertSame('10.000', $first->inventory->fresh()->quantity_on_hand);
        $this->assertSame(0, InventoryMovement::count());
    }

    public function test_packing_ready_and_legacy_completion_never_add_stock_until_explicit_receipt(): void
    {
        $actor = $this->actor();
        $this->actingAs($actor);
        $product = $this->product();
        $order = $this->order([$product->id => '2']);
        $before = $this->stock();
        foreach (['packing', 'ready', 'completed'] as $revision => $status) {
            $this->post(route('admin.supplier-orders.status', $order), ['status' => $status, 'revision' => $revision, 'confirm' => 1])->assertRedirect()->assertSessionHasNoErrors();
            $this->assertSame($status, $order->fresh()->status);
        }
        $this->assertSame($before, $this->stock());
        $this->get(route('admin.supplier-orders.show', $order))->assertOk()->assertSee('earlier manual-stock workflow');
        $this->post(route('admin.supplier-orders.receive', $order), $this->input(null, ['revision' => 3, 'add_to_inventory' => 0]))->assertSessionHasNoErrors();
        $this->assertSame($before, $this->stock());
        $this->assertSame($actor->name, $order->events()->where('to_status', 'ready')->first()->actor_name);
        $this->get(route('admin.supplier-orders.index', ['status' => 'received']))->assertOk()->assertSee($order->number);
    }
}
