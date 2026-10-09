<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class WarehouseWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $this->actingAs(User::factory()->create(['email'=>'test@example.test','account_type'=>'individual','phone'=>'555123','role_id'=>Role::where('name','Customer')->value('id'),'is_active'=>true]));
        $p = Product::create(['category_id' => 1, 'slug' => 'warehouse-cake', 'premium_marketing_name' => 'Warehouse Cake', 'qr_code' => 'WH001', 'is_active' => true, 'total_selling_price_cad' => 10]);
        $p->inventory()->create(['quantity_on_hand' => 10]);
        $this->postJson(route('cart.add', $p), ['quantity' => 2])->assertOk();
        $this->get('/checkout')->assertOk();
        $this->post('/checkout', ['checkout_token' => session('checkout_token'), 'first_name' => 'Test', 'last_name' => 'Customer', 'email' => 'test@example.test', 'phone' => '555123', 'address' => '10 Example Street', 'city' => 'Toronto', 'province' => 'Ontario', 'postal_code' => 'M1M1M1', 'country' => 'Canada'])->assertRedirect('/order-success');
        $o = Order::firstOrFail();
        $o->update(['delivery_cents' => 500, 'tax_cents' => 0, 'status' => 'placed', 'warehouse_round' => 0, 'warehouse_sent_at' => null]); // Legacy/manual-transfer fixture.
        $admin = User::factory()->create(['role_id' => Role::where('is_super', true)->value('id'), 'is_active' => true]);
        $worker = User::factory()->create(['role_id' => Role::where('name', 'Warehouse User')->value('id'), 'is_active' => true]);
        $this->actingAs($admin);

        return [$o, $p, $admin, $worker];
    }

    private function moveStatus($o, $status)
    {
        return $this->patchJson(route('admin.orders.status', $o), ['revision' => $o->fresh()->revision, 'status' => $status]);
    }

    private function pack($o, $action, $packed = 1, $note = '')
    {
        return $this->post(route('admin.orders.packing', $o), ['revision' => $o->fresh()->revision, 'action' => $action, 'items' => [$o->items()->first()->id => ['packed' => $packed, 'note' => $note]]]);
    }

    private function amend($o, $extra = [])
    {
        $i = $o->items()->first();

        return $this->post(route('admin.orders.amend', $o), array_replace(['revision' => $o->fresh()->revision, 'items' => [$i->id => ['quantity' => 1, 'unit_price' => '10.00']], 'fulfillment' => 'pickup', 'delivery' => '5.00', 'tax' => '0', 'tax_mode' => 'manual', 'reason' => 'Customer collects fewer items', 'first_name' => 'Test', 'last_name' => 'Customer', 'email' => 'test@example.test', 'phone' => '555', 'address' => '10 Example', 'city' => 'Toronto', 'province' => 'ON', 'postal_code' => 'M1M1M1', 'country' => 'Canada'], $extra));
    }

    public function test_warehouse_return_reassign_pack_dispatch_and_complete(): void
    {
        [$o,$p,$admin,$worker] = $this->fixture();
        $this->moveStatus($o, 'warehouse_pending')->assertOk();
        $this->actingAs($worker);
        $this->get('/admin/orders')->assertOk()->assertSee($o->number)->assertDontSee('Product subtotal (CAD)');
        $this->get(route('admin.orders.show', $o))->assertOk()->assertSee('Save Packing Progress')->assertDontSee('Unit price CAD');
        $this->get(route('admin.orders.print', $o))->assertOk()->assertDontSee('Final total');
        $this->pack($o, 'ready', 0)->assertSessionHasErrors('warehouse');
        $this->assertSame('warehouse_pending', $o->fresh()->status);
        $this->pack($o, 'return', 0, 'Only one cake available')->assertSessionHasNoErrors();
        $this->assertSame('warehouse_issue', $o->fresh()->status);
        $this->get(route('admin.orders.show', $o))->assertNotFound();
        $this->actingAs($admin);
        $this->get(route('admin.orders.show', $o))->assertOk()->assertSee('Only one cake available');
        $this->get('/admin/orders')->assertSee('warehouse-issue-row');
        $this->moveStatus($o, 'warehouse_pending')->assertOk();
        $this->assertNull($o->items()->first()->warehouse_note);
        $this->assertEquals(2, $o->fresh()->warehouse_round);
        $this->actingAs($worker);
        $this->pack($o, 'save')->assertSessionHasNoErrors();
        $this->assertEquals(1, $o->items()->first()->packed);
        $this->pack($o, 'ready')->assertSessionHasNoErrors();
        $this->actingAs($admin);
        $this->moveStatus($o, 'out_for_delivery')->assertOk();
        $this->moveStatus($o, 'delivered')->assertOk();
        $this->moveStatus($o, 'completed')->assertUnprocessable();
        $this->post(route('admin.orders.settlement', $o), ['revision' => $o->fresh()->revision, 'direction' => 'collect', 'method' => 'cash', 'amount' => '25', 'note' => 'Cash received', 'received' => 1])->assertSessionHasNoErrors();
        $this->moveStatus($o, 'completed')->assertOk();
        $this->assertSame('8.000', $p->inventory()->first()->quantity_on_hand);
    }

    public function test_warehouse_permissions_stale_and_item_tampering(): void
    {
        [$o,$p,$admin,$worker] = $this->fixture();
        $this->actingAs($worker);
        $this->get(route('admin.orders.show', $o))->assertNotFound();
        $this->get('/admin/reports/payments')->assertForbidden();
        $this->amend($o)->assertForbidden();
        $this->moveStatus($o, 'warehouse_pending')->assertForbidden();
        $this->actingAs($admin);
        $this->moveStatus($o, 'warehouse_pending')->assertOk();
        $this->actingAs($worker);
        $this->post(route('admin.orders.packing', $o), ['revision' => 0, 'action' => 'ready', 'items' => [$o->items()->first()->id => ['packed' => 1]]])->assertSessionHasErrors('warehouse');
        $this->post(route('admin.orders.packing', $o), ['revision' => 1, 'action' => 'ready', 'items' => [99999 => ['packed' => 1]]])->assertSessionHasErrors('warehouse');
        $this->pack($o, 'return', 0)->assertSessionHasErrors('warehouse');
        $this->assertSame('warehouse_pending', $o->fresh()->status);
    }

    public function test_amendment_adjusts_only_stock_difference_and_pickup_removes_delivery(): void
    {
        [$o,$p] = $this->fixture();
        $this->moveStatus($o, 'warehouse_pending')->assertOk();
        $this->pack($o, 'ready')->assertSessionHasNoErrors();
        $this->amend($o)->assertSessionHasNoErrors();
        $this->assertSame('9.000', $p->inventory()->first()->quantity_on_hand);
        $this->assertEquals(1000, $o->fresh()->final_total_cents);
        $this->assertSame('warehouse_issue', $o->fresh()->status);
        $this->assertEquals(0, $o->items()->first()->packed);
        $this->moveStatus($o, 'cancelled')->assertOk();
        $this->assertSame('10.000', $p->inventory()->first()->quantity_on_hand);
        $this->moveStatus($o, 'cancelled')->assertOk();
        $this->assertSame('10.000', $p->inventory()->first()->quantity_on_hand);
    }

    public function test_paid_amendment_preserves_received_money_and_records_refund(): void
    {
        [$o,$p] = $this->fixture();
        $o->update(['payment_status' => 'paid']);
        $this->amend($o)->assertSessionHasNoErrors();
        $this->assertEquals(2500, $o->fresh()->net_received_cents);
        $this->assertEquals(-1500, $o->fresh()->balance_cents);
        $this->get(route('admin.orders.show', $o))->assertOk()->assertSee('Refund due');
        $this->post(route('admin.orders.settlement', $o), ['revision' => $o->fresh()->revision, 'direction' => 'refund', 'method' => 'cash', 'amount' => '15', 'note' => 'Cash returned', 'received' => 1])->assertSessionHasNoErrors();
        $this->assertEquals(0, $o->fresh()->balance_cents);
        $this->assertEquals(1000, $o->fresh()->net_received_cents);
        $this->get('/admin/reports/payments')->assertOk()->assertViewHas('summary', fn ($s) => $s['cash']['gross'] === 2500 && $s['cash']['refunded'] === 1500);
    }

    public function test_insufficient_stock_and_removing_every_item_rollback(): void
    {
        [$o,$p] = $this->fixture();
        $id = $o->items()->first()->id;
        $this->amend($o, ['items' => [$id => ['quantity' => 20, 'unit_price' => '10']]])->assertSessionHasErrors('order');
        $this->assertSame('8.000', $p->inventory()->first()->quantity_on_hand);
        $this->amend($o, ['items' => [$id => ['quantity' => 0, 'unit_price' => '10']]])->assertSessionHasErrors('order');
        $this->assertEquals(2, $o->items()->first()->quantity);
        $this->assertSame('8.000', $p->inventory()->first()->quantity_on_hand);
    }

    public function test_partial_collection_then_mark_paid_does_not_double_count(): void
    {
        [$o] = $this->fixture();
        $this->post(route('admin.orders.settlement', $o), ['revision' => 0, 'direction' => 'collect', 'method' => 'cash', 'amount' => '5', 'note' => 'Deposit', 'received' => 1])->assertSessionHasNoErrors();
        $this->patch(route('admin.orders.update', $o), ['revision' => 1, 'status' => 'placed', 'payment_status' => 'paid', 'delivery' => '5', 'tax' => '0'])->assertSessionHasNoErrors();
        $this->assertEquals(2500, $o->fresh()->net_received_cents);
        $this->assertEquals(0, $o->fresh()->balance_cents);
    }

    public function test_cancellation_after_partial_refund_and_deposit_keeps_accounting_correct(): void
    {
        [$o] = $this->fixture();
        $o->update(['payment_status' => 'paid']);
        $this->amend($o)->assertSessionHasNoErrors();
        $this->post(route('admin.orders.settlement', $o), ['revision' => 1, 'direction' => 'refund', 'method' => 'cash', 'amount' => '15', 'note' => 'Partial refund', 'received' => 1])->assertSessionHasNoErrors();
        $this->patch(route('admin.orders.update', $o), ['revision' => 2, 'status' => 'cancelled', 'payment_status' => 'refunded', 'delivery' => '0', 'tax' => '0'])->assertSessionHasNoErrors();
        $this->assertEquals(0, $o->fresh()->net_received_cents);
        $this->assertEquals(0, $o->fresh()->balance_cents);
        $this->get('/admin/reports/payments')->assertViewHas('summary', fn ($s) => $s['cash']['gross'] === 2500 && $s['cash']['refunded'] === 2500);
    }

    public function test_add_product_reserves_stock_and_stale_edit_is_rejected(): void
    {
        [$o,$p] = $this->fixture();
        $new = Product::create(['category_id' => 1, 'slug' => 'extra-cake', 'premium_marketing_name' => 'Extra Cake', 'qr_code' => 'WH002', 'is_active' => true, 'total_selling_price_cad' => 7]);
        $new->inventory()->create(['quantity_on_hand' => 5]);
        $this->amend($o, ['add_items' => [['product_id' => $new->id, 'quantity' => 2, 'unit_price' => '']]])->assertSessionHasNoErrors();
        $this->assertEquals(2400, $o->fresh()->final_total_cents);
        $this->assertSame('3.000', $new->inventory()->first()->quantity_on_hand);
        $this->amend($o, ['revision' => 0])->assertSessionHasErrors('order');
        $this->assertEquals(2400, $o->fresh()->final_total_cents);
    }

    public function test_gateway_amount_remains_original_after_amendment_and_refund_is_provider_only(): void
    {
        [$o] = $this->fixture();
        $o->update(['payment_status' => 'paid', 'payment_method' => 'card']);
        $payment = $o->payment()->create(['reference' => (string) Str::uuid(), 'provider' => 'stripe', 'mode' => 'sandbox', 'status' => 'paid', 'amount_cents' => 2500, 'paid_at' => now(), 'expires_at' => now()->addHour(), 'transaction_id' => 'pi_warehouse']);
        $this->amend($o)->assertSessionHasNoErrors();
        $this->assertEquals(2500, $payment->fresh()->amount_cents);
        $this->assertEquals(-1500, $o->fresh()->balance_cents);
        $this->post(route('admin.orders.settlement', $o), ['revision' => 1, 'direction' => 'refund', 'method' => 'cash', 'amount' => '15', 'note' => 'Refund', 'received' => 1])->assertSessionHasErrors('order');
        $payment->update(['refunded_cents' => 1500, 'status' => 'partially_refunded']);
        $o->update(['payment_status' => 'partially_refunded']);
        $this->assertEquals(0, $o->fresh()->balance_cents);
        $this->moveStatus($o, 'warehouse_pending')->assertOk();
        $this->pack($o, 'ready')->assertSessionHasNoErrors();
        $this->moveStatus($o, 'delivered')->assertOk();
        $this->moveStatus($o, 'completed')->assertOk();
    }

    public function test_pending_gateway_cannot_be_amended_or_packed(): void
    {
        [$o] = $this->fixture();
        $o->update(['payment_status' => 'pending', 'payment_method' => 'card']);
        $o->payment()->create(['reference' => (string) Str::uuid(), 'provider' => 'stripe', 'mode' => 'sandbox', 'status' => 'pending', 'amount_cents' => 2500, 'expires_at' => now()->addHour()]);
        $this->amend($o)->assertSessionHasErrors('order');
        $this->moveStatus($o, 'warehouse_pending')->assertUnprocessable();
        $o->update(['status' => 'warehouse_pending']);
        $this->pack($o, 'ready')->assertSessionHasErrors('warehouse');
    }

    public function test_warehouse_sees_own_pos_sales_and_parked_orders_but_not_other_sales(): void
    {
        [$parked, $product, $admin, $worker] = $this->fixture();
        $this->moveStatus($parked, 'warehouse_pending')->assertOk();
        $worker->role->update(['permissions' => ['dashboard.view', 'warehouse.pack', 'pos.manage']]);
        $worker->unsetRelation('role');
        $this->actingAs($worker);
        $customer=User::factory()->create(['account_type'=>'individual','is_active'=>true])->customerRecord();
        $quote = $this->postJson(route('admin.pos.quote'), ['customer_id'=>$customer->id,'items' => [['id' => $product->id, 'quantity' => 1]], 'fulfillment' => 'pickup'])->assertOk()->json('quote');
        $this->postJson(route('admin.pos.store'), ['customer_id'=>$customer->id,'first_name'=>'POS Customer','email'=>'warehouse-pos@example.test','quote' => $quote, 'payment_method' => 'cash', 'payment_status' => 'paid'])->assertOk();
        $own = Order::where('created_by', $worker->id)->firstOrFail();
        $other = $own->replicate(['number', 'checkout_token', 'tracking_token']);
        $other->number = 'OTHER-POS';
        $other->checkout_token = (string) Str::uuid();
        $other->created_by = $admin->id;
        $other->save();
        $this->get('/admin/orders')->assertOk()->assertDontSee($own->number)->assertSee($parked->number)->assertDontSee('OTHER-POS');
        $this->get('/admin/orders/completed')->assertOk()->assertSee($own->number)->assertDontSee($parked->number)->assertDontSee('OTHER-POS')->assertSee('Created by you');
        $this->get('/admin/orders/completed?status=completed')->assertViewHas('orders', fn ($orders) => $orders->count() === 1 && $orders->first()->id === $own->id);
        $this->get('/admin/orders/completed?q=NONMATCHING')->assertViewHas('orders', fn ($orders) => $orders->isEmpty());
        $this->get('/admin/orders/completed?from=2000-01-01&to=2000-01-02')->assertViewHas('orders', fn ($orders) => $orders->isEmpty());
        $this->get(route('admin.orders.show', $own))->assertOk()->assertSee('Print Receipt')->assertSee('Warehouse Cake')->assertDontSee('Save Order Changes')->assertDontSee('Save Packing Progress');
        $this->get(route('admin.orders.print', $own))->assertOk()->assertSee('Sales Receipt');
        $this->get(route('admin.orders.show', $other))->assertNotFound();
        $this->get(route('admin.orders.print', $other))->assertNotFound();
        $this->moveStatus($own, 'warehouse_pending')->assertForbidden();
        $this->pack($own, 'ready')->assertSessionHasErrors('warehouse');
    }

    public function test_order_table_month_defaults_creator_sorting_and_exports_respect_access(): void
    {
        [$order,$product,$admin,$worker] = $this->fixture();
        $order->update(['source' => 'pos', 'created_by' => $worker->id, 'status' => 'completed']);
        $other = $order->replicate(['number', 'checkout_token', 'tracking_token']);
        $other->number = 'PRIVATE-ORDER';
        $other->checkout_token = (string) Str::uuid();
        $other->created_by = $admin->id;
        $other->save();
        $this->actingAs($worker);
        $this->get('/admin/orders/completed?sort=creator&direction=asc&per_page=10')->assertOk()->assertViewHas('from', now()->startOfMonth()->toDateString())->assertViewHas('to', now()->endOfMonth()->toDateString())->assertSee($worker->name)->assertSee('Created By')->assertSee('Source')->assertSee('Action')->assertDontSee('@include')->assertSee('Excel')->assertSee('PDF')->assertViewHas('orders', fn ($rows) => $rows->perPage() === 10 && $rows->total() === 1);
        foreach (['print', 'pdf'] as $format) {
            $this->get('/admin/orders/export?view=completed&format='.$format)->assertOk()->assertSee($order->number)->assertSee($worker->name)->assertDontSee('PRIVATE-ORDER')->assertDontSee('Subtotal CAD');
        }
        $response = $this->get('/admin/orders/export?view=completed&format=xlsx')->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'orders-test');
        file_put_contents($path, $response->streamedContent());
        $reader = new Reader;
        $reader->open($path);
        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
        }$reader->close();
        unlink($path);
        $this->assertCount(2, $rows);
        $this->assertSame('Created By', $rows[0][6]);
        $this->assertSame($worker->name, $rows[1][6]);
        $this->assertSame($order->number, $rows[1][0]);
        $this->get('/admin/orders/export?view=completed&format=print&q=NO-MATCH')->assertDontSee($order->number);
        $this->get('/admin/orders/completed?per_page=999')->assertSessionHasErrors('per_page');
        $this->get('/admin/orders/export?view=completed&format=print&sort=invalid')->assertSessionHasErrors('sort');
        $order->update(['created_at' => now()->subMonth()->startOfMonth()]);
        $this->get('/admin/orders/completed')->assertViewHas('orders', fn ($rows) => $rows->isEmpty());
        $this->get('/admin/orders/export?view=completed&format=print')->assertDontSee($order->number);
        $this->get('/admin/orders/completed?from='.now()->subMonth()->startOfMonth()->toDateString())->assertViewHas('orders', fn ($rows) => $rows->total() === 1);
    }

    public function test_admin_queues_include_every_creator_and_source(): void
    {
        [$pending,$product,$admin,$worker] = $this->fixture();
        $done = $pending->replicate(['number', 'checkout_token', 'tracking_token']);
        $done->number = 'DONE-WEBSITE';
        $done->checkout_token = (string) Str::uuid();
        $done->status = 'completed';
        $done->save();
        $sale = $done->replicate(['number', 'checkout_token', 'tracking_token']);
        $sale->number = 'DONE-POS';
        $sale->checkout_token = (string) Str::uuid();
        $sale->source = 'pos';
        $sale->created_by = $worker->id;
        $sale->save();
        $this->get('/admin/orders')->assertSee($pending->number)->assertDontSee('DONE-WEBSITE')->assertDontSee('DONE-POS');
        $response = $this->get('/admin/orders/completed')->assertOk()->assertSee('DONE-WEBSITE')->assertSee('DONE-POS')->assertSee('Quick Sale / POS')->assertSee($worker->name)->assertDontSee('@include');
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertEquals(8, $xpath->query('//table/thead/tr/th')->length);
        foreach ($xpath->query('//table/tbody/tr') as $row) {
            $this->assertEquals(8, $xpath->query('./td', $row)->length);
        }
        $this->actingAs($worker)->get('/admin/orders/completed')->assertSee('DONE-POS')->assertDontSee('DONE-WEBSITE');
        $this->get('/admin/orders')->assertDontSee($pending->number);
        $this->get('/admin/orders/export?view=completed&format=print')->assertSee('DONE-POS')->assertDontSee('DONE-WEBSITE');
    }

    public function test_shortage_notes_persist_yellow_and_block_ready_until_resolved(): void
    {
        [$order,$product,$admin,$worker] = $this->fixture();
        $this->moveStatus($order, 'warehouse_pending')->assertOk();
        $this->actingAs($worker);
        $this->pack($order, 'save', 1, 'Shortage: missing one unit')->assertSessionHasNoErrors();
        $this->assertEquals(0, $order->items()->first()->packed);
        $this->get(route('admin.orders.show', $order))->assertOk()->assertSee('has-issue')->assertSee('Scan with camera')->assertSee('Barcode: WH001');
        $this->pack($order, 'ready', 1, 'Shortage: missing one unit')->assertSessionHasErrors('warehouse');
        $this->pack($order, 'return', 0, 'Shortage: missing one unit')->assertSessionHasNoErrors();
        $this->actingAs($admin);
        $this->get(route('admin.orders.show', $order))->assertOk()->assertSee('has-issue')->assertSee('Shortage: missing one unit');
        $this->moveStatus($order, 'warehouse_pending')->assertOk();
        $this->actingAs($worker);
        $this->pack($order, 'ready', 1, '')->assertSessionHasNoErrors();
        $this->get(route('admin.orders.print', $order))->assertOk()->assertSee('MAMMA MIA CUCINA')->assertSee('Packing Slip')->assertSee('10 Example Street')->assertSee('Warehouse Cake')->assertDontSee('Subtotal');
    }

    public function test_resending_shortage_preserves_completed_items_and_their_packing_audit(): void
    {
        [$o,$p,$admin,$worker] = $this->fixture();
        $good = $o->items()->first();
        $missing = $o->items()->create(['name'=>'Missing cake','quantity'=>1,'unit_cents'=>1000,'line_cents'=>1000]);
        $this->moveStatus($o,'warehouse_pending')->assertOk();
        $this->actingAs($worker);
        $this->post(route('admin.orders.packing',$o),['revision'=>$o->fresh()->revision,'action'=>'return','items'=>[
            $good->id=>['packed'=>1,'note'=>''], $missing->id=>['packed'=>0,'note'=>'Shortage reported'],
        ]])->assertSessionHasNoErrors();
        $packedAt=$good->fresh()->packed_at;
        $this->actingAs($admin);
        $this->moveStatus($o,'warehouse_pending')->assertOk();
        $this->assertTrue((bool)$good->fresh()->packed);
        $this->assertEquals($worker->id,$good->fresh()->packed_by);
        $this->assertEquals($packedAt,$good->fresh()->packed_at);
        $this->assertFalse((bool)$missing->fresh()->packed);
        $this->actingAs($worker)->get(route('admin.orders.show',$o))->assertOk();
        $this->post(route('admin.orders.packing',$o),['revision'=>$o->fresh()->revision,'action'=>'ready','items'=>[
            $good->id=>['packed'=>1,'note'=>''], $missing->id=>['packed'=>0,'note'=>''],
        ]])->assertSessionHasErrors('warehouse');
    }

    public function test_amendment_preserves_unchanged_packing_and_only_resets_changed_quantity(): void
    {
        [$o,$p,$admin,$worker] = $this->fixture();
        $good=$o->items()->first();
        $other=$o->items()->create(['name'=>'Other cake','quantity'=>1,'unit_cents'=>1000,'line_cents'=>1000,'packed'=>true,'packed_by'=>$worker->id,'packed_at'=>now()]);
        $good->update(['packed'=>true,'packed_by'=>$worker->id,'packed_at'=>now()]);
        $at=$good->fresh()->packed_at;
        $this->amend($o,['items'=>[$good->id=>['quantity'=>2,'unit_price'=>'9.00'],$other->id=>['quantity'=>2,'unit_price'=>'10.00']]])->assertSessionHasNoErrors();
        $this->moveStatus($o,'warehouse_pending')->assertOk();
        $this->assertTrue((bool)$good->fresh()->packed);
        $this->assertEquals($worker->id,$good->fresh()->packed_by);
        $this->assertEquals($at,$good->fresh()->packed_at);
        $this->assertFalse((bool)$other->fresh()->packed);
        $this->assertNull($other->fresh()->packed_by);
        $this->assertNull($other->fresh()->packed_at);
    }

    public function test_payment_ledger_receipts_partial_collection_refund_and_access(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        [$o, $p, $admin, $worker] = $this->fixture();
        $this->get(route('admin.orders.payments', $o))->assertOk()->assertSee('No verified payments recorded');
        $receipt = fn () => \Illuminate\Http\UploadedFile::fake()->createWithContent('receipt.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a1ioAAAAASUVORK5CYII='));
        $data = ['revision'=>0,'direction'=>'collect','method'=>'card','reference'=>'TERMINAL-123','amount'=>'25','note'=>'Terminal payment received','received'=>1];
        $this->post(route('admin.orders.settlement',$o), $data+['receipt'=>$receipt()])->assertSessionHasNoErrors();
        $entry = $o->settlements()->firstOrFail();
        $this->assertEquals(2500,$entry->amount_cents);
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($entry->receipt_path);
        $url=route('admin.orders.payment-receipt',[$o,$entry]);
        $this->get($url)->assertOk()->assertHeader('X-Content-Type-Options','nosniff');
        $this->get(route('admin.orders.payments',$o))->assertOk()->assertSee('TERMINAL-123')->assertSee('+$25.00');
        $this->post(route('admin.orders.settlement',$o),$data+['receipt'=>$receipt()])->assertSessionHasErrors('order');
        $this->assertCount(1,\Illuminate\Support\Facades\Storage::disk('local')->allFiles('order-payment-receipts'));
        $this->amend($o)->assertSessionHasNoErrors();
        $this->assertEquals(-1500,$o->fresh()->balance_cents);
        $this->get(route('admin.orders.payments',$o))->assertOk()->assertSee('Refund due')->assertSee('$15.00');
        $this->post(route('admin.orders.settlement',$o),['revision'=>$o->fresh()->revision,'direction'=>'refund','method'=>'card','reference'=>'REFUND-123','amount'=>'15','note'=>'Terminal refund completed','received'=>1,'receipt'=>$receipt()])->assertSessionHasNoErrors();
        $this->assertEquals(0,$o->fresh()->balance_cents);
        $this->assertEquals(1000,$o->fresh()->net_received_cents);
        $this->get(route('admin.orders.payments',$o))->assertOk()->assertSee('−$15.00');
        $other=$o->replicate();$other->number='OTHER-PAYMENT';$other->checkout_token=(string) \Illuminate\Support\Str::uuid();$other->tracking_token=\Illuminate\Support\Str::random(48);$other->save();
        $this->get(route('admin.orders.payment-receipt',[$other,$entry]))->assertNotFound();
        $this->actingAs($worker)->get($url)->assertForbidden();
        $this->get(route('admin.orders.payments',$o))->assertForbidden();
    }

    public function test_etransfer_ledger_partial_full_collection_and_refund_after_quantity_reduction(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        [$o,$p,$admin,$worker]=$this->fixture();
        $o->update(['payment_method'=>'etransfer','status'=>'transfer_pending']);
        $receipt=fn()=>\Illuminate\Http\UploadedFile::fake()->createWithContent('transfer.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a1ioAAAAASUVORK5CYII='));
        $this->get(route('admin.orders.payments',$o))->assertOk()->assertSee('Record a payment')->assertSee('Partial payments remain pending');
        $data=['revision'=>0,'direction'=>'collect','method'=>'etransfer','reference'=>'BANK-1','amount'=>'10','note'=>'Bank deposit verified','received'=>1];
        $this->post(route('admin.orders.settlement',$o),$data)->assertSessionHasErrors('order');
        $this->actingAs($worker)->post(route('admin.orders.settlement',$o),$data)->assertForbidden();
        $this->actingAs($admin)->post(route('admin.orders.settlement',$o),$data+['receipt'=>$receipt()])->assertSessionHasNoErrors();
        $this->assertEquals(1000,$o->fresh()->net_received_cents);
        $this->assertEquals(1500,$o->fresh()->balance_cents);
        $this->assertSame('unpaid',$o->fresh()->payment_status);
        $this->assertSame('transfer_pending',$o->fresh()->status);
        $this->post(route('admin.orders.settlement',$o),$data+['receipt'=>$receipt()])->assertSessionHasErrors('order');
        $originalPath=$receipt()->store('transfer-receipts','local');
        $o->update(['transfer_receipt_path'=>$originalPath]);
        $data=array_replace($data,['revision'=>$o->fresh()->revision,'reference'=>'BANK-2','amount'=>'15']);
        $this->post(route('admin.orders.settlement',$o),$data)->assertSessionHasNoErrors();
        $savedReceipt=$o->settlements()->latest('id')->first()->receipt_path;
        $this->assertNotEquals($originalPath,$savedReceipt);
        $this->assertSame(\Illuminate\Support\Facades\Storage::disk('local')->get($originalPath),\Illuminate\Support\Facades\Storage::disk('local')->get($savedReceipt));
        $this->assertEquals(2500,$o->fresh()->net_received_cents);
        $this->assertEquals(0,$o->fresh()->balance_cents);
        $this->assertSame('paid',$o->fresh()->payment_status);
        $this->assertSame('warehouse_pending',$o->fresh()->status);
        $this->assertEquals(1,$o->fresh()->warehouse_round);
        $this->amend($o)->assertSessionHasNoErrors();
        $this->assertEquals(-1500,$o->fresh()->balance_cents);
        $this->get(route('admin.orders.payments',$o))->assertOk()->assertSee('Record a refund')->assertSee('Refund due');
        $refund=array_replace($data,['revision'=>$o->fresh()->revision,'direction'=>'refund','reference'=>'BANK-REFUND','amount'=>'16']);
        $this->post(route('admin.orders.settlement',$o),$refund)->assertSessionHasErrors('order');
        $refund['amount']='15';
        $this->post(route('admin.orders.settlement',$o),$refund+['receipt'=>$receipt()])->assertSessionHasNoErrors();
        $this->assertEquals(1000,$o->fresh()->net_received_cents);
        $this->assertEquals(0,$o->fresh()->balance_cents);
        $this->assertEquals([1000,1500,-1500],$o->settlements()->orderBy('id')->pluck('amount_cents')->all());
        $this->get(route('admin.orders.payments',$o))->assertOk()->assertSee('−$15.00');
    }
}
