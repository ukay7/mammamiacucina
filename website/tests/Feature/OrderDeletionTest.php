<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\EmailHistory;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\AdminDashboard;
use App\Services\BusinessReports;
use App\Services\CustomerEmails;
use App\Services\CustomerInvoicing;
use App\Services\OnlinePayments;
use App\Services\OrderDeletion;
use App\Services\StaffPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrderDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function super(): User
    {
        return User::factory()->create(['is_active' => true, 'role_id' => Role::where('is_super', true)->value('id')]);
    }

    private function order(array $extra = []): Order
    {
        return Order::create($extra + ['checkout_token' => (string) Str::uuid(), 'number' => 'DELETE-'.Str::random(8),
            'first_name' => 'Audit', 'last_name' => 'Buyer', 'email' => 'audit@example.test', 'phone' => '123',
            'address' => 'Street', 'city' => 'Toronto', 'province' => 'ON', 'postal_code' => 'M5V', 'country' => 'Canada',
            'subtotal_cents' => 1000, 'delivery_cents' => 0, 'tax_cents' => 0, 'currency' => 'CAD',
            'payment_method' => 'cash', 'payment_status' => 'unpaid', 'status' => 'warehouse_pending', 'revision' => 0]);
    }

    private function data(Order $order, bool $restock = false): array
    {
        return ['revision' => $order->revision, 'reason' => 'Client test order', 'confirm' => 1, 'restock_inventory' => $restock];
    }

    private function stock(Order $order): Product
    {
        $product = Product::create(['slug' => 'deletion-'.uniqid(), 'category_id' => Category::first()->id,
            'premium_marketing_name' => 'Stock deletion cake', 'is_active' => true]);
        $product->inventory()->create(['quantity_on_hand' => 7, 'low_stock_threshold' => 3]);
        $order->items()->create(['product_id' => $product->id, 'name' => 'Original cake', 'product_code' => 'AUDIT',
            'quantity' => 3, 'unit_cents' => 100, 'line_cents' => 300, 'stock_deducted' => true, 'inventory_shortage_quantity' => 1]);

        return $product;
    }

    public function test_only_super_admin_can_delete_or_view_deleted_orders_even_with_forged_page_permission(): void
    {
        $order = $this->order();
        $role = Role::create(['name' => 'Deletion manager', 'permissions' => [...StaffPermissions::normalize(['orders.view', 'orders.manage', 'access.admin.orders.index', 'access.admin.orders.completed']), 'access.admin.orders.deleted.index']]);
        $manager = User::factory()->create(['is_active' => true, 'role_id' => $role->id]);
        $this->actingAs($manager)->get(route('admin.orders.show', $order))->assertOk()->assertDontSee('Delete order')->assertDontSee('Deleted Orders');
        foreach (['admin.orders.index', 'admin.orders.completed'] as $route) {
            $this->get(route($route))->assertOk()->assertDontSee('data-order-delete', false)->assertDontSee('id="order-delete-dialog"', false);
        }
        $this->deleteJson(route('admin.orders.destroy', $order), $this->data($order))->assertForbidden();
        $this->get(route('admin.orders.deleted.index'))->assertForbidden();
        $this->assertNull($order->fresh()->deleted_at);
        $this->actingAs($this->super())->delete(route('admin.orders.destroy', $order), $this->data($order))->assertRedirect(route('admin.orders.deleted.index'));
        $this->actingAs($manager)->get(route('admin.orders.deleted.show', $order->id))->assertNotFound();
    }

    public function test_super_admin_can_open_deletion_from_pending_and_completed_lists(): void
    {
        $this->actingAs($this->super());
        foreach (['admin.orders.index' => 'warehouse_pending', 'admin.orders.completed' => 'completed'] as $route => $status) {
            $order = $this->order(['status' => $status, 'revision' => 4]);
            $this->get(route($route))->assertOk()
                ->assertSee('data-order-id="'.$order->id.'"', false)
                ->assertSee('data-order-number="'.$order->number.'"', false)
                ->assertSee('data-order-revision="4"', false)
                ->assertSee('data-delete-url="'.route('admin.orders.destroy', $order).'"', false)
                ->assertSee('id="order-delete-dialog"', false)
                ->assertSee('name="reason" maxlength="2000" rows="3" required', false)
                ->assertSee('name="restock_inventory" value="1" required', false)
                ->assertSee('name="restock_inventory" value="0" required', false)
                ->assertSee('name="confirm" value="1" required', false)
                ->assertDontSee(' checked', false);
            $this->assertNull($order->fresh()->deleted_at);
        }
    }

    public function test_blocked_deletion_returns_to_list_with_order_identity_and_reason(): void
    {
        $this->actingAs($this->super());
        $order = $this->order(['payment_method' => 'card', 'payment_status' => 'pending', 'status' => 'awaiting_payment']);
        Payment::create(['order_id' => $order->id, 'reference' => (string) Str::uuid(), 'provider' => 'stripe',
            'mode' => 'live', 'status' => 'pending', 'amount_cents' => 1000, 'currency' => 'CAD', 'expires_at' => now()->addHour()]);
        $url = route('admin.orders.index', ['q' => $order->number]);
        $this->from($url)->delete(route('admin.orders.destroy', $order), $this->data($order, true) + ['_delete_order_id' => $order->id])
            ->assertRedirect($url)->assertSessionHasErrors('order');
        $this->get($url)->assertOk()->assertSee('data-failed-order="'.$order->id.'"', false)
            ->assertSee('Resolve the in-progress gateway payment before deleting this order.')
            ->assertSee('Client test order')->assertDontSee('name="confirm" value="1" required checked', false);
        $this->assertNull($order->fresh()->deleted_at);
    }

    public function test_deletion_requires_reason_confirmation_stock_choice_and_current_revision(): void
    {
        $this->actingAs($this->super());
        $order = $this->order();
        $product = $this->stock($order);
        foreach (['reason', 'confirm', 'restock_inventory'] as $field) {
            $data = $this->data($order, true);
            unset($data[$field]);
            $this->deleteJson(route('admin.orders.destroy', $order), $data)->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->deleteJson(route('admin.orders.destroy', $order), array_replace($this->data($order), ['revision' => 99]))->assertUnprocessable();
        $this->deleteJson(route('admin.orders.destroy', $order), array_replace($this->data($order), ['reason' => '   ']))->assertUnprocessable();
        $this->assertSame('7.000', $product->inventory->fresh()->quantity_on_hand);
        $this->assertNull($order->fresh()->deleted_at);
    }

    public function test_stock_return_is_recorded_exactly_once_and_inventory_can_also_be_kept_unchanged(): void
    {
        $actor = $this->super();
        $this->actingAs($actor);
        $order = $this->order();
        $product = $this->stock($order);
        $this->delete(route('admin.orders.destroy', $order), $this->data($order, true))->assertSessionHasNoErrors();
        $this->assertSame('10.000', $product->inventory->fresh()->quantity_on_hand);
        $this->assertEquals(10, DB::table('warehouse_inventories')->where('product_id', $product->id)->sum('quantity_on_hand'));
        $this->assertDatabaseHas('inventory_movements', ['product_id' => $product->id, 'quantity_change' => 3, 'reason' => 'Deleted order '.$order->number]);
        $this->deleteJson(route('admin.orders.destroy', $order), $this->data($order, true))->assertNotFound();
        try {
            app(OrderDeletion::class)->remove($order, $this->data($order, true), $actor);
            $this->fail('Repeated deletion was accepted.');
        } catch (ValidationException) {
            $this->assertSame('10.000', $product->inventory->fresh()->quantity_on_hand);
        }
        $other = $this->order();
        $otherProduct = $this->stock($other);
        $this->delete(route('admin.orders.destroy', $other), $this->data($other))->assertSessionHasNoErrors();
        $this->assertSame('7.000', $otherProduct->inventory->fresh()->quantity_on_hand);
        $this->assertDatabaseHas('order_items', ['order_id' => $other->id, 'stock_deducted' => true]);
    }

    public function test_paid_order_invoice_payments_and_audit_are_preserved_only_on_the_deleted_page(): void
    {
        $actor = $this->super();
        $this->actingAs($actor);
        $order = $this->order(['status' => 'completed', 'payment_status' => 'paid', 'manual_collected_cents' => 1000, 'paid_at' => now()]);
        $this->stock($order);
        $invoice = app(CustomerInvoicing::class)->issue($order);
        $snapshot = $invoice->fresh()->snapshot;
        $entry = $order->settlements()->create(['amount_cents' => -100, 'method' => 'cash', 'note' => 'Retained refund', 'user_id' => $actor->id]);
        app(CustomerEmails::class)->order($order, 'order_confirmation', 'order-created:'.$order->id);
        $emailId = EmailHistory::where('order_id', $order->id)->value('id');
        $this->delete(route('admin.orders.destroy', $order), $this->data($order))->assertSessionHasNoErrors();
        $deleted = Order::onlyTrashed()->findOrFail($order->id);
        $this->assertSame('completed', $deleted->status);
        $this->assertSame('paid', $deleted->payment_status);
        $this->assertSame(900, $deleted->net_received_cents);
        $this->assertSame($actor->id, $deleted->deleted_by);
        $this->assertSame($snapshot, $invoice->fresh()->snapshot);
        $this->assertDatabaseHas('order_settlements', ['id' => $entry->id, 'amount_cents' => -100]);
        $this->assertDatabaseHas('email_history', ['id' => $emailId, 'status' => 'cancelled']);
        $this->assertNull(EmailHistory::find($emailId));
        $this->get(route('admin.orders.deleted.index'))->assertOk()->assertSee($order->number)->assertDontSee('Archived Orders');
        $this->get(route('admin.orders.deleted.show', $order->id))->assertOk()->assertSee('Deleted · View only')->assertSee($invoice->label)
            ->assertSee('Original cake')->assertSee('Retained refund')->assertSee('Client test order')->assertDontSee('Save order')->assertDontSee('Record a payment')->assertDontSee('name="confirm"', false);
        foreach (['admin.orders.index', 'admin.orders.completed', 'admin.orders.show', 'admin.orders.payments', 'admin.orders.print'] as $route) {
            $response = $this->get(route($route, str_ends_with($route, '.index') || str_ends_with($route, '.completed') ? [] : [$order->id]));
            if (str_ends_with($route, '.index') || str_ends_with($route, '.completed')) {
                $response->assertOk()->assertDontSee($order->number);
            } else {
                $response->assertNotFound();
            }
        }
        $this->get(route('admin.invoices.show', $invoice))->assertNotFound();
        $this->get(URL::temporarySignedRoute('invoice.document', now()->addHour(), ['invoice' => $invoice->id]))->assertNotFound();
        $this->postJson(route('admin.invoices.email', $invoice))->assertNotFound();
        foreach (['admin.orders.amend', 'admin.orders.settlement', 'admin.orders.packing'] as $route) {
            $this->postJson(route($route, $order->id), [])->assertNotFound();
        }
        $this->patchJson(route('admin.orders.status', $order->id), ['revision' => 1, 'status' => 'cancelled'])->assertNotFound();
        $this->post('/admin/orders/'.$order->id.'/restore')->assertStatus(405);
        $this->post('/admin/orders/'.$order->id.'/archive')->assertStatus(405);
    }

    public function test_every_business_report_and_export_excludes_deleted_money_and_orders(): void
    {
        $this->actingAs($this->super());
        $live = $this->order(['payment_status' => 'paid', 'manual_collected_cents' => 1000, 'paid_at' => now()]);
        $deleted = $this->order(['payment_status' => 'paid', 'manual_collected_cents' => 900000, 'paid_at' => now(), 'subtotal_cents' => 900000]);
        $this->stock($deleted);
        $this->delete(route('admin.orders.destroy', $deleted), $this->data($deleted))->assertSessionHasNoErrors();
        session()->forget('status');
        $reports = app(BusinessReports::class);
        $filters = ['from' => today()->subDay()->toDateString(), 'to' => today()->addDay()->toDateString()];
        foreach (array_keys(BusinessReports::TYPES) as $kind) {
            $result = $reports->report($kind, $filters);
            if (in_array($kind, ['payments', 'methods'])) {
                $this->assertEquals(1000, $result['summary']['received']);
            }
            $this->get(route('admin.reports.business', ['kind' => $kind]))->assertOk()->assertDontSee($deleted->number);
            $this->assertStringNotContainsString($deleted->number, $this->get(route('admin.reports.business.export', ['kind' => $kind]))->assertOk()->streamedContent());
        }
        $this->assertSame(1, (int) $reports->orderSummary($reports->orders())['orders']);
        $this->assertSame(1000, (int) $reports->orderSummary($reports->orders())['value']);
        $dashboard = app(AdminDashboard::class)->build(auth()->user(), 30);
        $this->assertSame(1, $dashboard['sales']['orders']);
        $this->assertSame(1000, $dashboard['sales']['value']);
        $this->get(route('admin.reports.payments'))->assertOk()->assertSee($live->number)->assertDontSee($deleted->number);
        $this->assertStringNotContainsString($deleted->number, $this->get(route('admin.reports.payments.export'))->assertOk()->streamedContent());
        $this->get(route('admin.orders.export', ['format' => 'print']))->assertOk()->assertDontSee($deleted->number);
        $this->getJson(route('admin.orders.export', ['format' => 'xlsx', 'view' => 'deleted']))->assertUnprocessable();
        $this->get(route('admin.reports.inventory'))->assertOk()->assertDontSee($deleted->number);
        $this->getJson(route('admin.inventory.orders', $deleted->items()->first()->product_id))->assertOk()->assertJsonCount(0, 'orders');
        $this->assertStringNotContainsString('Stock deletion cake', $this->get(route('admin.reports.inventory.export', ['report' => 'usage']))->assertOk()->streamedContent());
    }

    public function test_customer_balances_history_credit_usage_and_tracking_exclude_deleted_orders(): void
    {
        $customerUser = User::factory()->create(['account_type' => 'individual', 'is_active' => true, 'role_id' => Role::where('name', 'Customer')->value('id')]);
        $customer = $customerUser->customerRecord();
        $deleted = $this->order(['customer_id' => $customer->id, 'created_by' => $customerUser->id, 'payment_method' => 'credit']);
        $live = $this->order(['customer_id' => $customer->id, 'created_by' => $customerUser->id]);
        $this->actingAs($this->super())->delete(route('admin.orders.destroy', $deleted), $this->data($deleted))->assertSessionHasNoErrors();
        session()->forget('status');
        $this->assertSame(1000, app(CustomerInvoicing::class)->balances($customer)['pending']);
        $this->get(route('admin.customers.show', $customer))->assertOk()->assertSee($live->number)->assertDontSee($deleted->number);
        $this->actingAs($customerUser)->get(route('customer.orders'))->assertOk()->assertSee($live->number)->assertDontSee($deleted->number);
        $this->get(route('customer.order', $deleted->id))->assertNotFound();
        $this->get(route('order.track', $deleted->tracking_token))->assertNotFound();
        $this->get(route('admin.orders.deleted.show', $deleted->id))->assertNotFound();
    }

    public function test_gateway_records_stay_read_only_and_pending_gateway_deletion_is_blocked(): void
    {
        $this->actingAs($this->super());
        $order = $this->order(['payment_method' => 'card', 'payment_status' => 'paid']);
        $payment = Payment::create(['order_id' => $order->id, 'reference' => (string) Str::uuid(), 'provider' => 'stripe',
            'mode' => 'live', 'status' => 'pending', 'amount_cents' => 1000, 'currency' => 'CAD', 'expires_at' => now()->addHour()]);
        $this->deleteJson(route('admin.orders.destroy', $order), $this->data($order))->assertUnprocessable()->assertJsonValidationErrors('order');
        $payment->update(['status' => 'paid', 'paid_at' => now()]);
        $this->delete(route('admin.orders.destroy', $order), $this->data($order))->assertSessionHasNoErrors();
        $snapshot = $payment->fresh()->getAttributes();
        Http::preventStrayRequests();
        app(OnlinePayments::class)->sync($payment, true, true);
        app(OnlinePayments::class)->paypalRefund($payment, ['id' => 'late-event', 'amount' => ['value' => '10.00']]);
        $this->assertSame($snapshot, $payment->fresh()->getAttributes());
        foreach (['reconcile', 'cancel', 'refund', 'resolve'] as $action) {
            $this->postJson(route('admin.payments.'.$action, $payment), [])->assertNotFound();
        }
        $this->artisan('payments:reconcile')->expectsOutput('Checked 0 payment records; 0 deferred.')->assertSuccessful();
    }
}
