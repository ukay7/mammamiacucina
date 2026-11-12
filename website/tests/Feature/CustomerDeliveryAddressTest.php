<?php

namespace Tests\Feature;

use App\Models\{Customer, GeneralSetting, Order, Product, Role, User};
use App\Services\{CustomerDeliveryAddress, DocumentTheme};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CustomerDeliveryAddressTest extends TestCase
{
    use RefreshDatabase;

    protected function fixture(): array
    {
        GeneralSetting::findOrFail(1)->update(['matrix_delivery_enabled'=>false,'pickup_address'=>'Store pickup','delivery_cents'=>500,'tax_basis_points'=>0]);
        $user=User::factory()->create(['name'=>'Alex Buyer','account_type'=>'business','business_approved_at'=>now(),'email_verified_at'=>now(),'phone'=>'555123','is_active'=>true,'role_id'=>Role::where('name','Customer')->value('id')]);
        $customer=$user->customerRecord();
        $company=['address'=>'10 Company Street','city'=>'Toronto','province'=>'ON','postal_code'=>'M2N 1A1','country'=>'Canada'];
        $warehouse=['address'=>'25 Warehouse Road, Unit 4','city'=>'Scarborough','province'=>'ON','postal_code'=>'M1P 1A1','country'=>'Canada'];
        $customer->update($company+['business_name'=>'Example Bakery','delivery_address'=>$warehouse]);
        $admin=User::factory()->create(['role_id'=>Role::where('is_super',true)->value('id'),'is_active'=>true]);
        $product=Product::create(['category_id'=>1,'slug'=>'delivery-address-cake','premium_marketing_name'=>'Delivery Cake','qr_code'=>'DELIVERY-ADDRESS','is_active'=>true,'total_selling_price_cad'=>10,'business_selling_price_cad'=>8]);
        return compact('user','customer','company','warehouse','admin','product');
    }

    protected function checkout(array $f, string $type='alternate', array $address=[]): array
    {
        $this->actingAs($f['user']);
        $this->postJson(route('cart.add',$f['product']),['quantity'=>1])->assertOk();
        $this->get('/checkout')->assertOk()->assertSee('Where should we deliver this order?')->assertSee('Company address')->assertSee('Different delivery address');
        return ['checkout_token'=>session('checkout_token'),'fulfillment'=>'delivery','delivery_address_type'=>$type,
            'first_name'=>'Alex','last_name'=>'Buyer','email'=>$f['user']->email,'phone'=>'555123','payment_method'=>'cash'] + ($address ?: $f['warehouse']);
    }

    protected function posQuote(array $f, string $type='alternate', array $address=[], string $fulfillment='delivery'): array
    {
        $this->actingAs($f['admin']);
        return $this->postJson(route('admin.pos.quote'),['customer_id'=>$f['customer']->id,'items'=>[['id'=>$f['product']->id,'quantity'=>1]],
            'fulfillment'=>$fulfillment,'delivery_address_type'=>$type] + ($address ?: $f['warehouse']))->assertOk()->json();
    }

    protected function posInput(array $f, array $quote, string $type='alternate', array $address=[]): array
    {
        return ['customer_id'=>$f['customer']->id,'quote'=>$quote['quote'],'delivery_address_type'=>$type,'phone'=>'555123','payment_method'=>'cash','payment_status'=>'unpaid'] + ($address ?: $f['warehouse']);
    }

    private function assertCompanyUnchanged(array $f): void
    {
        $this->assertSame($f['company'],$f['customer']->fresh()->only(CustomerDeliveryAddress::FIELDS));
    }

    public function test_customer_company_choice_uses_profile_and_preserves_saved_warehouse(): void
    {
        $f=$this->fixture(); $data=$this->checkout($f,'company');
        // Even an altered payload cannot change the company address through checkout.
        $this->post('/checkout',$data)->assertRedirect('/order-success')->assertSessionHasNoErrors();
        $order=Order::firstOrFail();
        $this->assertSame($f['company'],$order->only(CustomerDeliveryAddress::FIELDS));
        $this->assertCompanyUnchanged($f);
        $this->assertSame($f['warehouse'],$f['customer']->fresh()->delivery_address);
        $this->assertSame(500,$order->delivery_cents);
    }

    public function test_customer_and_admin_share_an_editable_warehouse_without_rewriting_order_snapshots(): void
    {
        $f=$this->fixture(); $warehouse=array_replace($f['warehouse'],['address'=>'30 New Warehouse Road']);
        $data=$this->checkout($f,'alternate',$warehouse);
        $this->post('/checkout',$data)->assertRedirect('/order-success')->assertSessionHasNoErrors();
        $first=Order::firstOrFail();
        $this->assertSame($warehouse,$first->only(CustomerDeliveryAddress::FIELDS));
        $this->assertSame($warehouse,$f['customer']->fresh()->delivery_address); $this->assertCompanyUnchanged($f);
        $this->actingAs($f['admin'])->getJson(route('admin.pos.products',['customer_id'=>$f['customer']->id]))->assertOk()
            ->assertJsonPath('customer.address',$f['company']['address'])->assertJsonPath('customer.delivery_address.address',$warehouse['address']);
        $secondAddress=array_replace($warehouse,['address'=>'40 Replacement Warehouse']);
        $quote=$this->posQuote($f,'alternate',$secondAddress);
        $this->postJson(route('admin.pos.store'),$this->posInput($f,$quote,'alternate',$secondAddress))->assertOk();
        $this->assertSame($secondAddress,$f['customer']->fresh()->delivery_address); $this->assertCompanyUnchanged($f);
        $this->assertSame($warehouse,$first->fresh()->only(CustomerDeliveryAddress::FIELDS));
        $this->assertStringContainsString($warehouse['address'],app(DocumentTheme::class)->order($first->fresh())['delivery']);
        $this->checkout($f);
        $this->get('/checkout')->assertOk()->assertSee($secondAddress['address'])->assertSee($f['company']['address']);
    }

    public function test_admin_company_choice_and_quote_changes_preserve_both_addresses(): void
    {
        $f=$this->fixture(); $quote=$this->posQuote($f,'company',$f['company']);
        $this->postJson(route('admin.pos.store'),$this->posInput($f,$quote,'alternate'))->assertUnprocessable();
        $this->assertDatabaseCount('orders',0);
        $this->postJson(route('admin.pos.store'),$this->posInput($f,$quote,'company'))->assertOk();
        $this->assertSame($f['company'],Order::firstOrFail()->only(CustomerDeliveryAddress::FIELDS));
        $this->assertCompanyUnchanged($f); $this->assertSame($f['warehouse'],$f['customer']->fresh()->delivery_address);
        $quote=$this->posQuote($f);
        $this->postJson(route('admin.pos.store'),$this->posInput($f,$quote,'alternate',array_replace($f['warehouse'],['address'=>'Unreviewed street'])))->assertUnprocessable();
        $this->assertDatabaseCount('orders',1); $this->assertCompanyUnchanged($f);
    }

    public function test_failed_validation_pickup_and_duplicate_submission_do_not_replace_saved_addresses(): void
    {
        $f=$this->fixture(); $data=$this->checkout($f);
        foreach (['address'=>'','postal_code'=>['invalid'],'city'=>str_repeat('x',101)] as $key=>$value) {
            $this->postJson('/checkout',array_replace($data,[$key=>$value]))->assertUnprocessable();
        }
        $this->postJson('/checkout',array_replace($data,['delivery_address_type'=>'other']))->assertUnprocessable();
        $this->assertDatabaseCount('orders',0); $this->assertSame($f['warehouse'],$f['customer']->fresh()->delivery_address);
        $this->post('/checkout',array_replace($data,['fulfillment'=>'pickup','address'=>'Ignored pickup address']))->assertRedirect('/order-success');
        $this->assertSame('',Order::firstOrFail()->address); $this->assertCompanyUnchanged($f);
        $this->assertSame($f['warehouse'],$f['customer']->fresh()->delivery_address);
        $data=$this->checkout($f,'alternate',array_replace($f['warehouse'],['address'=>'Saved once']));
        $this->post('/checkout',$data)->assertRedirect('/order-success');
        $this->post('/checkout',array_replace($data,['address'=>'Retry must not overwrite']))->assertRedirect('/order-success');
        $this->assertSame('Saved once',$f['customer']->fresh()->delivery_address['address']); $this->assertDatabaseCount('orders',2);
        $quote=$this->posQuote($f,'alternate',[],'pickup');
        $this->postJson(route('admin.pos.store'),$this->posInput($f,$quote))->assertOk();
        $this->assertSame('Saved once',$f['customer']->fresh()->delivery_address['address']); $this->assertCompanyUnchanged($f);
    }

    public function test_matrix_charge_follows_selected_destination_in_both_checkouts(): void
    {
        $f=$this->fixture();
        DB::table('delivery_postal_zones')->insert([['prefix'=>'M2N','zone'=>8],['prefix'=>'M1P','zone'=>3]]);
        DB::table('delivery_services')->insert(['code'=>'test','name'=>'Test courier']);
        DB::table('delivery_rates')->insert([['service_code'=>'test','from_zone'=>8,'to_zone'=>8,'amount_cents'=>1000],['service_code'=>'test','from_zone'=>8,'to_zone'=>3,'amount_cents'=>2500]]);
        GeneralSetting::findOrFail(1)->update(['matrix_delivery_enabled'=>true,'warehouse_postal_code'=>'M2N']);
        $data=$this->checkout($f)+['delivery_service'=>'test'];
        $this->postJson(route('checkout.delivery-quote'),$f['company']+['delivery_service'=>'test'])->assertOk()->assertJsonPath('delivery_cents',1000);
        $this->post('/checkout',$data)->assertSessionHasErrors('delivery_service'); $this->assertDatabaseCount('orders',0);
        $this->postJson(route('checkout.delivery-quote'),$f['warehouse']+['delivery_service'=>'test'])->assertOk()->assertJsonPath('delivery_cents',2500);
        $this->post('/checkout',$data)->assertRedirect('/order-success'); $this->assertSame(2500,Order::firstOrFail()->delivery_cents);
        $quote=$this->posQuote($f,'company',$f['company']+['delivery_service'=>'test']); $this->assertSame(1000,$quote['delivery']);
        $this->postJson(route('admin.pos.store'),$this->posInput($f,$quote,'company',$f['company']+['delivery_service'=>'test']))->assertOk();
        $this->assertSame(1000,Order::latest('id')->firstOrFail()->delivery_cents); $this->assertCompanyUnchanged($f);
        $quote=$this->posQuote($f,'alternate',$f['warehouse']+['delivery_service'=>'test']); $this->assertSame(2500,$quote['delivery']);
    }

    public function test_customer_isolation_and_profile_edits_keep_the_separate_delivery_address(): void
    {
        $f=$this->fixture(); $other=User::factory()->create(['account_type'=>'individual','is_active'=>true])->customerRecord();
        $other->update(['delivery_address'=>array_replace($f['warehouse'],['address'=>'Other private warehouse'])]);
        $data=$this->checkout($f); $this->get('/checkout')->assertOk()->assertDontSee('Other private warehouse');
        $data['checkout_token']=session('checkout_token');
        $this->post('/checkout',$data+['customer_id'=>$other->id])->assertRedirect('/order-success');
        $this->assertSame($f['customer']->id,Order::firstOrFail()->customer_id);
        $this->assertSame('Other private warehouse',$other->fresh()->delivery_address['address']);
        // Editing the company/profile address remains separate from the warehouse.
        $this->actingAs($f['admin']);
        $f['customer']->update(['address'=>'Updated company office']);
        $this->assertSame($f['warehouse'],$f['customer']->fresh()->delivery_address);
    }

    public function test_missing_company_address_and_legacy_checkout_store_only_separate_destination(): void
    {
        $f=$this->fixture(); $f['customer']->update(['address'=>null,'delivery_address'=>null]);
        $data=$this->checkout($f,'company');
        $this->post('/checkout',$data)->assertSessionHasErrors('address');
        unset($data['delivery_address_type']);
        $this->post('/checkout',$data)->assertRedirect('/order-success');
        $this->assertNull($f['customer']->fresh()->address);
        $this->assertSame($f['warehouse'],$f['customer']->fresh()->delivery_address);
    }

    public function test_company_address_changed_during_checkout_requires_a_new_review(): void
    {
        $f=$this->fixture(); $data=$this->checkout($f,'company');
        $f['customer']->update(['address'=>'Company moved during checkout']);
        $this->post('/checkout',$data)->assertSessionHasErrors('address');
        $this->assertDatabaseCount('orders',0);
        $quote=$this->posQuote($f,'company',$f['company']);
        $f['customer']->update(['address'=>'Company moved again']);
        $this->postJson(route('admin.pos.store'),$this->posInput($f,$quote,'company'))->assertUnprocessable();
        $this->assertDatabaseCount('orders',0);
        $this->assertSame($f['warehouse'],$f['customer']->fresh()->delivery_address);
    }
}
