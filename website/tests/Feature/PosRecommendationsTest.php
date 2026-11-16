<?php

namespace Tests\Feature;

use App\Models\{Customer,Order,Payment,Product,Role,User};
use App\Support\BusinessTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PosRecommendationsTest extends TestCase
{
    use RefreshDatabase;
    private Customer $buyer;
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->staff=User::factory()->create(['is_active'=>true,'role_id'=>Role::create(['name'=>'Counter recommendations','permissions'=>['pos.manage']])->id]);
        $this->buyer=User::factory()->create(['is_active'=>true,'account_type'=>'business','business_approved_at'=>now()])->customerRecord();
        $this->actingAs($this->staff);
    }

    private function product(string $name,?int $stock=100,array $extra=[]): Product
    {
        $p=Product::create($extra+['category_id'=>1,'slug'=>Str::slug($name),'premium_marketing_name'=>$name,'is_active'=>true,'total_selling_price_cad'=>20,'business_selling_price_cad'=>15]);
        $p->inventory()->create(['quantity_on_hand'=>$stock,'low_stock_threshold'=>0]);return $p;
    }

    private function order(Product $p,int $qty=1,array $extra=[]): Order
    {
        $o=Order::create($extra+['customer_id'=>$this->buyer->id,'checkout_token'=>(string)Str::uuid(),'number'=>'REC-'.Str::random(10),
            'first_name'=>'Buyer','last_name'=>'Test','email'=>$this->buyer->email,'phone'=>'0','address'=>'','city'=>'','province'=>'','postal_code'=>'','country'=>'Canada',
            'status'=>'delivered','subtotal_cents'=>1000*$qty,'delivery_cents'=>0,'tax_cents'=>0,'ordered_at'=>now()->subDays(2)]);
        $o->items()->create(['product_id'=>$p->id,'name'=>$p->premium_marketing_name,'quantity'=>$qty,'unit_cents'=>1000,'line_cents'=>1000*$qty]);return $o;
    }

    private function sandbox(Order $order): void
    {
        Payment::create(['order_id'=>$order->id,'reference'=>Str::uuid(),'provider'=>'stripe','mode'=>'sandbox','status'=>'paid','amount_cents'=>1000,'expires_at'=>now()->addDay(),'paid_at'=>now()]);
    }

    private function recommendations(?Customer $customer=null)
    {
        return $this->getJson(route('admin.pos.recommendations',['customer_id'=>($customer??$this->buyer)->id]))->assertOk();
    }

    public function test_favourites_count_distinct_completed_orders_for_the_selected_customer(): void
    {
        $often=$this->product('Often');$bulk=$this->product('Bulk');$recent=$this->product('Recent');$old=$this->product('Old');
        for($i=0;$i<3;$i++)$this->order($often);
        $duplicate=$this->order($recent,1,['ordered_at'=>now()->subDay()]);
        $duplicate->items()->create(['product_id'=>$recent->id,'name'=>'Recent','quantity'=>5,'unit_cents'=>1000,'line_cents'=>5000]);
        $this->order($bulk,90);$this->order($old,1,['ordered_at'=>now()->subYear()]);
        $other=User::factory()->create(['is_active'=>true,'account_type'=>'individual'])->customerRecord();
        for($i=0;$i<4;$i++)$this->order($old,1,['customer_id'=>$other->id]);
        foreach(['cancelled','warehouse_pending','awaiting_payment'] as $status)$this->order($bulk,1,['status'=>$status]);
        $deleted=$this->order($bulk);DB::table('orders')->where('id',$deleted->id)->update(['deleted_at'=>now()]);
        $this->sandbox($this->order($bulk));$this->order($bulk,1,['ordered_at'=>now()->addDay()]);
        $rows=$this->recommendations()->assertJsonCount(3,'frequent')->json('frequent');
        $this->assertSame([$often->id,$recent->id,$bulk->id],array_column($rows,'id'));
        $this->assertSame([3,1,1],array_column($rows,'order_count'));
        $this->recommendations($other)->assertJsonCount(1,'frequent')->assertJsonPath('frequent.0.id',$old->id)->assertJsonPath('frequent.0.order_count',4);
    }

    public function test_excess_stock_uses_90_days_of_completed_sales_and_current_sellable_inventory(): void
    {
        $this->travelTo(now()->setDate(2026,11,16)->setTime(18,0));
        $a=$this->product('High unsold',100);$b=$this->product('High slow',80);$c=$this->product('Some sales',60);
        $this->order($b,10);$this->order($c,20);
        $this->order($b,500,['ordered_at'=>BusinessTime::today()->subDays(90)->utc()]);
        $this->order($b,1,['ordered_at'=>BusinessTime::today()->subDays(89)->utc()]);
        $this->order($b,500,['status'=>'warehouse_pending']);$this->sandbox($this->order($a,500));
        $fast=$this->product('Fast',1000);$this->order($fast,1001);
        $this->product('Inactive',10000,['is_active'=>false]);$this->product('No price',10000,['business_selling_price_cad'=>null,'total_selling_price_cad'=>null]);
        $this->product('Untracked',null);$this->product('Zero stock',0);$this->product('Negative stock',-1);
        $low=$this->product('Low stock alert',200);$low->inventory()->update(['low_stock_threshold'=>250]);
        $rows=$this->recommendations()->assertJsonCount(3,'slow')->assertJsonPath('days',90)->json('slow');
        $this->assertSame([$a->id,$b->id,$c->id],array_column($rows,'id'));
        $this->assertEquals([0,11,20],array_column($rows,'sold_units'));
        $this->travelBack();
    }

    public function test_suggestions_use_customer_prices_and_pos_permissions_and_do_not_mutate_orders(): void
    {
        $p=$this->product('Discounted');$this->order($p);
        DB::table('customer_product_discounts')->insert(['customer_id'=>$this->buyer->id,'product_id'=>$p->id,'enabled'=>true,'discount_bps'=>1000]);
        $this->recommendations()->assertJsonPath('frequent.0.unit_cents',1350)->assertJsonPath('slow.0.unit_cents',1350);
        $this->assertDatabaseCount('orders',1);$this->assertDatabaseCount('inventory_movements',0);$this->assertDatabaseCount('email_history',0);
        $this->getJson(route('admin.pos.recommendations'))->assertUnprocessable();
        $this->getJson(route('admin.pos.recommendations',['customer_id'=>999999]))->assertUnprocessable();
        $this->staff->role->update(['permissions'=>['orders.view']]);$this->staff->unsetRelation('role');
        $this->getJson(route('admin.pos.recommendations',['customer_id'=>$this->buyer->id]))->assertForbidden();
    }

    public function test_no_history_and_unsellable_favourites_return_honest_empty_groups(): void
    {
        $this->recommendations()->assertJsonPath('frequent',[])->assertJsonPath('slow',[]);
        $unavailable=$this->product('Unavailable',0);$this->order($unavailable);
        $inactive=$this->product('Disabled',200,['is_active'=>false]);$this->order($inactive);
        $this->recommendations()->assertJsonPath('frequent',[])->assertJsonPath('slow',[]);
        $available=$this->product('Untracked favourite',null);$this->order($available);
        $this->recommendations()->assertJsonPath('frequent.0.id',$available->id)->assertJsonCount(1,'frequent')->assertJsonPath('slow',[]);
    }
}
