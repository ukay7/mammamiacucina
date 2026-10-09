<?php
namespace Tests\Feature;

use App\Models\{GeneralSetting, Order, Product, Role, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TransferCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function checkout(string $fulfillment = 'delivery', string $method = 'etransfer'): array
    {
        $user = User::factory()->create(['account_type'=>'individual','is_active'=>true,'role_id'=>Role::where('name','Customer')->value('id')]);
        $this->actingAs($user);
        GeneralSetting::findOrFail(1)->update(['pickup_address'=>'123 Test Street, Toronto, ON', 'delivery_cents'=>500, 'tax_basis_points'=>500]);
        $product = Product::create(['category_id'=>1,'slug'=>'transfer-cake','premium_marketing_name'=>'Transfer Cake','qr_code'=>'TRANSFER','is_active'=>true,'total_selling_price_cad'=>10]);
        $product->inventory()->create(['quantity_on_hand'=>10]);
        $this->postJson(route('cart.add',$product),['quantity'=>2])->assertOk();
        $this->get('/checkout')->assertOk()->assertSee('E-transfer')->assertSee('Pick up');
        return [$user, $product, ['checkout_token'=>session('checkout_token'), 'fulfillment'=>$fulfillment, 'payment_method'=>$method, 'first_name'=>'Test','last_name'=>'Customer','email'=>$user->email,'phone'=>'123','address'=>'45 Delivery Street','city'=>'Toronto','province'=>'ON','postal_code'=>'M1P 1A1','country'=>'Canada']];
    }

    private function image(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a1ioAAAAASUVORK5CYII='));
    }

    private function admin(): User
    {
        return User::factory()->create(['is_active'=>true,'role_id'=>Role::where('is_super',true)->value('id')]);
    }

    public function test_pickup_bypasses_matrix_and_preserves_customer_address(): void
    {
        [$user,$product,$data] = $this->checkout('pickup','cash');
        $user->customerRecord()->update(['address'=>'Saved address']);
        GeneralSetting::findOrFail(1)->update(['matrix_delivery_enabled'=>true]);
        $this->get('/checkout')->assertOk();
        $data['checkout_token']=session('checkout_token');
        foreach(['address','city','province','postal_code','country'] as $field) unset($data[$field]);
        $this->post('/checkout',$data)->assertRedirect('/order-success');
        $order=Order::firstOrFail();
        $this->assertSame('pickup',$order->fulfillment);
        $this->assertEquals(0,$order->delivery_cents);
        $this->assertEquals(2100,$order->final_total_cents);
        $this->assertSame('warehouse_pending',$order->status);
        $this->assertSame('Saved address',$user->customerRecord()->fresh()->address);
        $this->assertSame('123 Test Street, Toronto, ON',$order->pickup_address);
        $this->get('/order-success')->assertOk()->assertSee('Cash on pick up')->assertSee('123 Test Street');
        $this->get(route('customer.order',$order))->assertOk()->assertSee('123 Test Street');
        $this->get('/order-print')->assertOk()->assertSee('123 Test Street');
        $this->post('/checkout',$data)->assertRedirect('/order-success');
        $this->assertDatabaseCount('orders',1);
    }

    public function test_transfer_receipt_is_private_and_admin_verification_releases_order_once(): void
    {
        Storage::fake('local');
        [$user,$product,$data]=$this->checkout();
        $this->post('/checkout',$data)->assertRedirect('/order-success');
        $order=Order::firstOrFail();
        $this->assertSame('transfer_pending',$order->status);
        $this->assertSame('unpaid',$order->payment_status);
        $this->assertEquals(2600,$order->final_total_cents);
        $this->assertDatabaseCount('payments',0);
        $this->get('/order-success')->assertOk()->assertSee('Please upload your e-transfer receipt');
        $this->get(route('customer.order',$order))->assertOk()->assertSee('Upload receipt');
        $worker=User::factory()->create(['is_active'=>true,'role_id'=>Role::where('name','Warehouse User')->value('id')]);
        $this->actingAs($worker)->get(route('admin.orders.show',$order))->assertNotFound();
        $this->get(route('orders.transfer-receipt',$order))->assertForbidden();
        $other=User::factory()->create(['is_active'=>true,'account_type'=>'individual','role_id'=>Role::where('name','Customer')->value('id')]);
        $this->actingAs($other)->post(route('orders.transfer-receipt.store',$order),['revision'=>0,'receipt'=>$this->image('receipt.png')])->assertNotFound();
        $this->actingAs($user)->post(route('orders.transfer-receipt.store',$order),['revision'=>0,'receipt'=>$this->image('receipt.png')])->assertRedirect()->assertSessionHasNoErrors();
        $order->refresh();
        Storage::disk('local')->assertExists($order->transfer_receipt_path);
        $this->assertSame('unpaid',$order->payment_status);
        $this->get(route('orders.transfer-receipt',$order))->assertOk()->assertHeader('Cache-Control','no-store, private');
        $this->post(route('orders.transfer.verify',$order),['revision'=>$order->revision,'received'=>1])->assertForbidden();
        $admin=$this->admin();
        $this->actingAs($admin)->get(route('admin.orders.show',$order))->assertOk()->assertSee('Verify payment &amp; send to warehouse',false);
        $this->post(route('orders.transfer.verify',$order),['revision'=>0,'received'=>1])->assertSessionHasErrors('order');
        $this->post(route('orders.transfer.verify',$order),['revision'=>$order->revision,'received'=>1])->assertRedirect()->assertSessionHasNoErrors();
        $order->refresh();
        $this->assertSame('paid',$order->payment_status);
        $this->assertSame('warehouse_pending',$order->status);
        $this->assertEquals(0,$order->balance_cents);
        $this->assertEquals(1,$order->warehouse_round);
        $this->assertNotNull($order->paid_at);
        $this->post(route('orders.transfer.verify',$order),['revision'=>$order->revision,'received'=>1])->assertSessionHasErrors('receipt');
        $this->actingAs($user)->post(route('orders.transfer-receipt.store',$order),['revision'=>$order->revision,'receipt'=>$this->image('replacement.png')])->assertSessionHasErrors('receipt');
        $this->actingAs($worker)->get(route('admin.orders.show',$order))->assertOk();
        $this->assertEquals(1,$order->fresh()->warehouse_round);
        $this->assertDatabaseCount('order_events',2);
    }

    public function test_receipt_replacement_and_validation_do_not_mark_paid(): void
    {
        Storage::fake('local');
        [$user,$product,$data]=$this->checkout('pickup');
        $this->post('/checkout',$data)->assertRedirect('/order-success');
        $order=Order::firstOrFail();
        $url=route('orders.transfer-receipt.store',$order);
        $this->post($url,['revision'=>0,'receipt'=>UploadedFile::fake()->create('bad.html',1,'text/html')])->assertSessionHasErrors('receipt');
        $this->post($url,['revision'=>0,'receipt'=>$this->image('big.png')->size(5121)])->assertSessionHasErrors('receipt');
        $this->post($url,['revision'=>0,'receipt'=>$this->image('receipt.png')])->assertSessionHasNoErrors();
        $old=$order->fresh()->transfer_receipt_path;
        $this->post($url,['revision'=>0,'receipt'=>$this->image('stale.png')])->assertSessionHasErrors('receipt');
        $this->assertCount(1,Storage::disk('local')->allFiles('transfer-receipts'));
        $this->actingAs($this->admin())->post($url,['revision'=>1,'receipt'=>UploadedFile::fake()->createWithContent('receipt.pdf',"%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF")])->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($old);
        $this->assertSame('unpaid',$order->fresh()->payment_status);
        $this->assertSame('transfer_pending',$order->fresh()->status);
    }

    public function test_disabled_methods_missing_address_and_unverified_warehouse_transition_are_rejected(): void
    {
        [$user,$product,$data]=$this->checkout();
        foreach(['card','paypal'] as $method) $this->post('/checkout',array_replace($data,['payment_method'=>$method]))->assertSessionHasErrors('payment_method');
        $this->post('/checkout',array_replace($data,['address'=>'']))->assertSessionHasErrors('address');
        GeneralSetting::findOrFail(1)->update(['pickup_address'=>null]);
        $this->get('/checkout')->assertOk();
        $data['checkout_token']=session('checkout_token');
        $this->post('/checkout',array_replace($data,['fulfillment'=>'pickup']))->assertSessionHasErrors('fulfillment');
        $this->post('/checkout',$data)->assertRedirect('/order-success');
        $order=Order::firstOrFail();
        $this->actingAs($this->admin());
        $update=['revision'=>0,'status'=>'warehouse_pending','payment_status'=>'unpaid','delivery'=>'5','tax'=>'1'];
        $this->patch(route('admin.orders.update',$order),$update)->assertSessionHasErrors('order');
        $this->patch(route('admin.orders.update',$order),array_replace($update,['payment_status'=>'paid']))->assertSessionHasErrors('order');
        $this->assertSame('transfer_pending',$order->fresh()->status);
        $this->patch(route('admin.orders.update',$order),array_replace($update,['status'=>'cancelled']))->assertSessionHasNoErrors();
        $this->assertEquals(10,$product->inventory()->first()->quantity_on_hand);
    }
}
