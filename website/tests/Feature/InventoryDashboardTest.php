<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class InventoryDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function actor(array $permissions=['inventory.view','inventory.manage']): User
    {
        $role=Role::create(['name'=>'Inventory '.Str::random(5),'permissions'=>$permissions,'is_super'=>false]);
        return User::factory()->create(['role_id'=>$role->id,'is_active'=>true]);
    }

    private function product(?float $quantity=5,float $threshold=10): Product
    {
        $product=Product::create(['category_id'=>1,'slug'=>'inventory-'.Str::lower(Str::random(6)),'premium_marketing_name'=>'Dashboard Cake','product_code'=>'DASH-1','qr_code'=>'DASH-QR','uom'=>'PZ','is_active'=>true,'total_selling_price_cad'=>10]);
        $product->inventory()->create(['quantity_on_hand'=>$quantity,'low_stock_threshold'=>$threshold]);
        return $product;
    }

    public function test_inline_adjustment_requires_reason_records_history_and_rejects_stale_write(): void
    {
        $this->actingAs($this->actor());$product=$this->product(5,10);
        $this->postJson(route('admin.inventory.adjust',$product),['action'=>'set','quantity'=>12,'expected_quantity'=>5,'low_stock_threshold'=>4,'reason'=>'Physical count correction'])
            ->assertOk()->assertJsonPath('quantity',12)->assertJsonPath('threshold',4);
        $this->assertDatabaseHas('inventory_movements',['product_id'=>$product->id,'quantity_change'=>7,'reason'=>'Physical count correction']);
        $this->postJson(route('admin.inventory.adjust',$product),['action'=>'set','quantity'=>9,'expected_quantity'=>5,'low_stock_threshold'=>4,'reason'=>'Stale attempt'])->assertUnprocessable();
        $this->assertSame('12.000',$product->inventory->fresh()->quantity_on_hand);
        $this->postJson(route('admin.inventory.adjust',$product),['action'=>'set','quantity'=>9,'expected_quantity'=>12,'low_stock_threshold'=>4,'reason'=>''])->assertUnprocessable();
    }

    public function test_dashboard_reports_stock_health_shortages_usage_and_exports(): void
    {
        $this->actingAs($this->actor());$product=$this->product(-2,10);
        $order=Order::create(['checkout_token'=>(string)Str::uuid(),'number'=>'mmc-9001','first_name'=>'Stock','last_name'=>'Customer','email'=>'stock@example.test','phone'=>'555','address'=>'','city'=>'','province'=>'','postal_code'=>'','country'=>'','status'=>'warehouse_pending','source'=>'website','fulfillment'=>'pickup','payment_method'=>'cash','payment_status'=>'unpaid','subtotal_cents'=>2000,'delivery_cents'=>0,'tax_cents'=>0,'ordered_at'=>now()]);
        $order->items()->create(['product_id'=>$product->id,'name'=>$product->premium_marketing_name,'product_code'=>$product->product_code,'qr_code'=>$product->qr_code,'quantity'=>2,'unit_cents'=>1000,'line_cents'=>2000,'stock_deducted'=>true,'inventory_shortage_quantity'=>2]);
        $this->get(route('admin.reports.inventory'))->assertOk()->assertSee('Inventory health')->assertSee('Orders accepted with inventory shortage')->assertSee('mmc-9001')->assertSee('Dashboard Cake');
        $this->get(route('admin.reports.inventory.export',['report'=>'snapshot']))->assertOk()->assertDownload();
        $this->get(route('admin.reports.inventory.export',['report'=>'usage']))->assertOk()->assertDownload();
        $this->getJson(route('admin.inventory.orders',$product))->assertOk()->assertJsonPath('orders.0.number','mmc-9001')->assertJsonPath('orders.0.shortage',2);
    }

    public function test_inventory_view_can_open_dashboard_but_cannot_adjust_stock(): void
    {
        $this->actingAs($this->actor(['inventory.view']));$product=$this->product();
        $this->get(route('admin.inventory.index'))->assertOk()->assertSee('Inventory Dashboard')->assertDontSee('inv-adjust-form');
        $this->get(route('admin.reports.inventory'))->assertOk();
        $this->post(route('admin.inventory.adjust',$product),[])->assertForbidden();
    }
}
