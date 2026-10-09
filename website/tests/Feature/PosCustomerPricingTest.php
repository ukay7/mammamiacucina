<?php
namespace Tests\Feature;
use App\Models\{User,Role,Product,Order};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PosCustomerPricingTest extends TestCase
{
    use RefreshDatabase;
    private function fixtures(): array
    {
        $role=Role::create(['name'=>'POS warehouse tester','permissions'=>['pos.manage','warehouse.pack']]);
        $staff=User::factory()->create(['is_active'=>true,'role_id'=>$role->id]);
        $individual=User::factory()->create(['account_type'=>'individual','is_active'=>true,'phone'=>'555123']);
        $business=User::factory()->create(['account_type'=>'business','business_approved_at'=>now(),'is_active'=>true,'phone'=>'555123']);
        $p=Product::create(['category_id'=>1,'slug'=>'tier-test','premium_marketing_name'=>'Tier Cake','qr_code'=>'TIER','is_active'=>true,'total_selling_price_cad'=>25,'business_selling_price_cad'=>15]);
        $p->inventory()->create(['quantity_on_hand'=>10]);
        $this->actingAs($staff);
        return [$staff,$individual->customerRecord(),$business->customerRecord(),$p];
    }
    private function quote($customer,$p): string
    {
        return $this->postJson(route('admin.pos.quote'),['customer_id'=>$customer->id,'items'=>[['id'=>$p->id,'quantity'=>1]],'fulfillment'=>'pickup'])->assertOk()->json('quote');
    }
    public function test_customer_price_is_used_for_search_scan_and_saved_lines_and_quote_is_bound(): void
    {
        [$staff,$individual,$business,$p]=$this->fixtures();
        $this->getJson(route('admin.pos.products',['q'=>'TIER']))->assertUnprocessable();
        foreach([[$individual,2500],[$business,1500]] as [$customer,$price]) {
            foreach([0,1] as $scan) $this->getJson(route('admin.pos.products',['customer_id'=>$customer->id,'q'=>'TIER','scan'=>$scan]))->assertOk()->assertJsonPath('products.0.unit_cents',$price);
        }
        $quote=$this->quote($business,$p);
        $payload=['quote'=>$quote,'customer_id'=>$individual->id,'payment_method'=>'cash','payment_status'=>'paid'];
        $this->postJson(route('admin.pos.store'),$payload)->assertUnprocessable();
        $payload['customer_id']=$business->id;
        $this->postJson(route('admin.pos.store'),$payload+['unit_cents'=>1])->assertOk()->assertJsonPath('total',1500);
        $this->assertDatabaseHas('order_items',['product_id'=>$p->id,'unit_cents'=>1500]);
        $this->postJson(route('admin.pos.store'),$payload)->assertOk();
        $this->assertDatabaseCount('orders',1);
        $this->assertEquals(9,$p->inventory()->first()->quantity_on_hand);
    }
    public function test_changed_approval_or_price_requires_new_review(): void
    {
        [$staff,$individual,$business,$p]=$this->fixtures();
        $quote=$this->quote($business,$p);
        $business->user->forceFill(['business_approved_at'=>null])->save();
        $this->getJson(route('admin.pos.products',['customer_id'=>$business->id,'q'=>'TIER']))->assertUnprocessable();
        $payload=['quote'=>$quote,'customer_id'=>$business->id,'payment_method'=>'cash','payment_status'=>'paid'];
        $this->postJson(route('admin.pos.store'),$payload)->assertUnprocessable();
        $business->user->forceFill(['business_approved_at'=>now()])->save();
        $p->update(['business_selling_price_cad'=>18]);
        $this->postJson(route('admin.pos.store'),$payload)->assertUnprocessable();
        $this->assertDatabaseCount('orders',0);
        $this->assertEquals(10,$p->inventory()->first()->quantity_on_hand);
    }
    public function test_pos_transfer_uses_shared_verification_and_cannot_be_self_approved_by_worker(): void
    {
        Storage::fake('local');
        [$staff,$individual,$business,$p]=$this->fixtures();
        $quote=$this->quote($individual,$p);
        $response=$this->postJson(route('admin.pos.store'),['quote'=>$quote,'customer_id'=>$individual->id,'payment_method'=>'etransfer','payment_status'=>'paid'])->assertOk()->assertJsonPath('revision',0);
        $order=Order::firstOrFail();
        $this->assertSame('transfer_pending',$order->status);$this->assertSame('unpaid',$order->payment_status);
        $this->get(route('admin.pos.orders'))->assertOk()->assertSee($order->number);
        $this->get($response->json('order_url'))->assertOk()->assertSee('Upload receipt');
        $file=UploadedFile::fake()->createWithContent('receipt.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a1ioAAAAASUVORK5CYII='));
        $this->postJson($response->json('receipt_url'),['revision'=>0,'receipt'=>$file])->assertOk();
        $order->refresh();
        $this->get(route('orders.transfer-receipt',$order))->assertOk();
        $this->post(route('orders.transfer.verify',$order),['revision'=>$order->revision,'received'=>1])->assertForbidden();
        $other=User::factory()->create(['is_active'=>true,'role_id'=>$staff->role_id]);
        $this->actingAs($other)->get(route('admin.pos.order',$order))->assertForbidden();
        $this->get(route('orders.transfer-receipt',$order))->assertForbidden();
        $admin=User::factory()->create(['is_active'=>true,'role_id'=>Role::where('is_super',true)->value('id')]);
        $this->actingAs($admin)->post(route('orders.transfer.verify',$order),['revision'=>$order->revision,'received'=>1])->assertSessionHasNoErrors();
        $order->refresh();$this->assertSame('paid',$order->payment_status);$this->assertSame('warehouse_pending',$order->status);
        $this->get(route('admin.reports.payments',['method'=>'etransfer']))->assertOk()->assertViewHas('summary',fn($summary)=>$summary['etransfer']['gross']===2500 && $summary['etransfer']['pending']===0);
        $this->actingAs($staff)->get(route('admin.orders.show',$order))->assertOk()->assertSee('Save Packing Progress');
        $items=$order->items->mapWithKeys(fn($i)=>[$i->id=>['packed'=>1,'note'=>'']])->all();
        $this->post(route('admin.orders.packing',$order),['revision'=>$order->revision,'action'=>'ready','items'=>$items])->assertSessionHasNoErrors();
        $order->refresh();
        $this->post(route('admin.pos.handover',$order),['revision'=>$order->revision,'handed_over'=>1])->assertSessionHasNoErrors();
        $this->assertSame('completed',$order->fresh()->status);
        $this->assertEquals(0,$order->fresh()->balance_cents);
    }
}
