<?php

namespace Tests\Feature;

use App\Models\{GeneralSetting, Order, Product, Role, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EtransferAccountTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_active'=>true,'role_id'=>Role::where('is_super',true)->value('id')]);
    }

    private function settingsData(string $email): array
    {
        $settings=GeneralSetting::findOrFail(1);
        return ['revision'=>$settings->revision,'delivery'=>number_format($settings->delivery_cents/100,2,'.',''),
            'tax'=>number_format($settings->tax_basis_points/100,2,'.',''),'etransfer_email'=>$email];
    }

    public function test_receiving_email_is_configurable_and_shared_by_customer_checkout_pos_and_receipt_uploads(): void
    {
        $settings=GeneralSetting::findOrFail(1);
        $this->assertSame('info@mammamiacucina.ca',$settings->etransfer_email);
        $contactEmail=$settings->email;
        $admin=$this->admin(); $this->actingAs($admin);
        $this->get(route('admin.settings.general'))->assertOk()->assertSee('E-transfer receiving email')->assertSee('info@mammamiacucina.ca');
        $data=$this->settingsData('Transfers@Example.test');
        $this->put(route('admin.settings.update'),$data)->assertSessionHasNoErrors();
        $this->assertSame('transfers@example.test',$settings->fresh()->etransfer_email);
        $this->assertSame($contactEmail,$settings->fresh()->email);
        $this->put(route('admin.settings.update'),$data)->assertSessionHasErrors('settings');
        $this->get(route('admin.pos.index'))->assertOk()->assertSee('pos-etransfer-account')->assertSee('E-transfer account: transfers@example.test');

        $user=User::factory()->create(['account_type'=>'individual','is_active'=>true,'role_id'=>Role::where('name','Customer')->value('id')]);
        $this->actingAs($user);
        $settings->update(['pickup_address'=>'123 Test Street, Toronto']);
        $product=Product::create(['category_id'=>1,'slug'=>'etransfer-instructions-cake','premium_marketing_name'=>'Test Cake','qr_code'=>'ETRANSFER-QA','is_active'=>true,'total_selling_price_cad'=>10]);
        $product->inventory()->create(['quantity_on_hand'=>5]);
        $this->postJson(route('cart.add',$product),['quantity'=>1])->assertOk();
        $this->get('/checkout')->assertOk()->assertSee('checkout-etransfer-account')->assertSee('E-transfer account: transfers@example.test');
        $this->post('/checkout',['checkout_token'=>session('checkout_token'),'fulfillment'=>'pickup','payment_method'=>'etransfer',
            'first_name'=>'Sample','last_name'=>'Customer','email'=>$user->email,'phone'=>'123'])->assertRedirect('/order-success');
        $order=Order::firstOrFail();
        $this->get('/order-success')->assertOk()->assertSee('E-transfer account: transfers@example.test');
        $this->get(route('customer.order',$order))->assertOk()->assertSee('E-transfer account: transfers@example.test');
        $this->actingAs($admin)->get(route('admin.orders.show',$order))->assertOk()->assertSee('E-transfer account: transfers@example.test');

        $this->put(route('admin.settings.update'),$this->settingsData('new-account@example.test'))->assertSessionHasNoErrors();
        $this->get(route('admin.orders.payments',$order))->assertOk()->assertSee('E-transfer account: new-account@example.test')->assertDontSee('E-transfer account: transfers@example.test')
            ->assertSee('data-etransfer-payment-form')->assertSee('Verify that the e-transfer was received in this account before clearing the payment.');
        $this->get(route('admin.customers.receipts.create',$user->customerRecord()))->assertOk()->assertSee('data-etransfer-only')->assertSee('E-transfer account: new-account@example.test')
            ->assertSee('Verify that the e-transfer was received in this account before clearing the payment.')->assertDontSee('E-transfer account: transfers@example.test');
        $this->actingAs($user)->get(route('customer.order',$order))->assertOk()->assertSee('E-transfer account: new-account@example.test');
        foreach (['paid','cancelled','cheque'] as $state) {
            $example=clone $order;
            if ($state==='paid') $example->payment_status='paid';
            elseif ($state==='cancelled') $example->status='cancelled';
            else $example->payment_method='cheque';
            $this->assertStringNotContainsString('E-transfer account:',view('partials.transfer-receipt',['order'=>$example])->render());
        }
    }

    public function test_invalid_or_unauthorized_account_changes_are_rejected(): void
    {
        $this->actingAs($this->admin());
        foreach (['','not-an-email',"pay@example.test\r\nBcc: other@example.test"] as $email) {
            $this->put(route('admin.settings.update'),$this->settingsData($email))->assertSessionHasErrors('etransfer_email');
        }
        $this->assertSame('info@mammamiacucina.ca',GeneralSetting::find(1)->etransfer_email);
        $user=User::factory()->create(['account_type'=>'individual','is_active'=>true,'role_id'=>Role::where('name','Customer')->value('id')]);
        $this->actingAs($user)->put(route('admin.settings.update'),$this->settingsData('other@example.test'))->assertRedirect(route('customer.orders'));
        $this->assertSame('info@mammamiacucina.ca',GeneralSetting::find(1)->etransfer_email);
    }
}
