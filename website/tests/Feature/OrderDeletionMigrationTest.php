<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderDeletionMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh', ['--seed' => true]);
        RefreshDatabaseState::$migrated = false;
    }

    public function test_legacy_archives_and_order_emails_move_to_deleted_audit_without_changing_stock_or_money(): void
    {
        $actor = User::factory()->create(['role_id' => Role::where('is_super', true)->value('id'), 'is_active' => true]);
        $order = Order::create(['checkout_token' => (string) Str::uuid(), 'number' => 'OLD-ARCHIVE',
            'first_name' => 'Legacy', 'last_name' => 'Buyer', 'email' => 'old@example.test', 'phone' => '123',
            'address' => 'Street', 'city' => 'Toronto', 'province' => 'ON', 'postal_code' => 'M5V', 'country' => 'Canada',
            'subtotal_cents' => 1000, 'delivery_cents' => 0, 'tax_cents' => 0, 'currency' => 'CAD',
            'payment_method' => 'cash', 'payment_status' => 'paid', 'manual_collected_cents' => 1000, 'status' => 'completed']);
        $migration = require database_path('migrations/2026_10_25_120000_replace_order_archive_with_soft_deletion.php');
        $migration->down();
        DB::table('orders')->where('id', $order->id)->update(['archived_at' => now(), 'archived_by' => $actor->id]);
        $email = DB::table('email_history')->insertGetId(['event_key' => 'order-created:'.$order->id,
            'recipient' => $order->email, 'type' => 'order_confirmation', 'subject' => 'Old order', 'status' => 'accepted',
            'created_at' => now(), 'updated_at' => now()]);
        $stock = DB::table('inventories')->pluck('quantity_on_hand', 'id')->all();
        $migration->up();
        $deleted = Order::onlyTrashed()->findOrFail($order->id);
        $this->assertSame($actor->id, $deleted->deleted_by);
        $this->assertSame('Previously archived; moved to Deleted Orders.', $deleted->deletion_reason);
        $this->assertFalse($deleted->deletion_restock);
        $this->assertSame(1000, $deleted->net_received_cents);
        $this->assertSame($stock, DB::table('inventories')->pluck('quantity_on_hand', 'id')->all());
        $this->assertDatabaseHas('email_history', ['id' => $email, 'order_id' => $order->id, 'status' => 'accepted']);
        $this->assertNull(Order::find($order->id));
        $this->actingAs($actor)->get(route('admin.orders.deleted.index'))->assertOk()->assertSee('OLD-ARCHIVE');
    }
}
