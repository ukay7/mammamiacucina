<?php

namespace Tests\Feature;

use App\Models\{GeneralSetting, Order, Role, User};
use App\Services\{BusinessCustomerIcon, DocumentTheme, OrderDeliveryDocuments};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Blade, Storage};
use Illuminate\Support\Str;
use Tests\TestCase;

class BusinessCustomerIconTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role_id'=>Role::where('is_super',true)->value('id'),'is_active'=>true]);
    }

    private function data(array $extra = []): array
    {
        $s=GeneralSetting::findOrFail(1);
        return $extra+['revision'=>$s->revision,'delivery'=>(string)($s->delivery_cents/100),'tax'=>(string)($s->tax_basis_points/100)];
    }

    private function upload(): string
    {
        $this->put(route('admin.settings.update'), $this->data(['business_customer_icon'=>UploadedFile::fake()->image('business.png',320,160)]))
            ->assertSessionHasNoErrors()->assertRedirect(route('admin.settings.general'));
        return GeneralSetting::findOrFail(1)->business_customer_icon_path;
    }

    public function test_upload_replacement_removal_and_stale_edits_keep_files_consistent(): void
    {
        Storage::fake('local');
        $this->actingAs($this->admin());
        $old=$this->upload();
        Storage::disk('local')->assertExists($old);
        $size=getimagesize(Storage::disk('local')->path($old));
        $this->assertSame([128,128],[$size[0],$size[1]]);
        $this->assertSame('image/png',$size['mime']);
        $this->get(route('site.business-customer-icon'))->assertOk()->assertHeader('Content-Type','image/png')->assertHeader('X-Content-Type-Options','nosniff');
        $this->get(route('admin.settings.general'))->assertOk()->assertSee('Business Customer Icon')->assertSee('Preview: Business customer');
        $stale=$this->data();
        $new=$this->upload();
        $this->assertNotSame($old,$new);
        Storage::disk('local')->assertMissing($old);
        $this->put(route('admin.settings.update'),$stale+['business_customer_icon'=>UploadedFile::fake()->image('stale.jpg')])->assertSessionHasErrors('settings');
        $this->assertSame([$new],Storage::disk('local')->allFiles('branding/business-customers'));
        $this->put(route('admin.settings.update'),$this->data())->assertSessionHasNoErrors();
        $this->assertSame($new,GeneralSetting::find(1)->business_customer_icon_path);
        $this->put(route('admin.settings.update'),$this->data(['remove_business_customer_icon'=>1]))->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($new);
        $this->assertNull(GeneralSetting::find(1)->business_customer_icon_path);
        $this->get(route('site.business-customer-icon'))->assertOk()->assertHeader('Content-Type','image/png');
    }

    public function test_upload_validates_type_size_dimensions_and_settings_permission(): void
    {
        Storage::fake('local');
        $this->actingAs($this->admin());
        foreach ([UploadedFile::fake()->createWithContent('script.svg','<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            UploadedFile::fake()->image('large.png')->size(2049),UploadedFile::fake()->image('wide.png',2049,10)] as $file) {
            $this->put(route('admin.settings.update'),$this->data(['business_customer_icon'=>$file]))->assertSessionHasErrors('business_customer_icon');
        }
        $this->put(route('admin.settings.update'),$this->data(['business_customer_icon'=>UploadedFile::fake()->image('both.png'),'remove_business_customer_icon'=>1]))->assertSessionHasErrors('business_customer_icon');
        $role=Role::create(['name'=>'No branding access','permissions'=>['orders.view']]);
        $this->actingAs(User::factory()->create(['role_id'=>$role->id,'is_active'=>true]));
        $this->put(route('admin.settings.update'),$this->data(['business_customer_icon'=>UploadedFile::fake()->image('denied.png')]))->assertForbidden();
        $this->assertNull(GeneralSetting::find(1)->business_customer_icon_path);
        $this->assertEmpty(Storage::disk('local')->allFiles('branding/business-customers'));
    }

    public function test_customer_type_controls_icons_and_documents_embed_the_same_image_without_changing_order(): void
    {
        Storage::fake('local');
        $this->actingAs($this->admin());
        $this->upload();
        $role=Role::where('name','Customer')->value('id');
        $business=User::factory()->create(['name'=>'Business Owner','account_type'=>'business','role_id'=>$role,'is_active'=>true,'email_verified_at'=>now()])->customerRecord();
        $business->update(['business_name'=>'Bakery & <Cafe>']);
        $individual=User::factory()->create(['name'=>'Individual Person','account_type'=>'individual','role_id'=>$role,'is_active'=>true])->customerRecord();
        $individual->update(['business_name'=>'Stale company label']);
        foreach ([$business,$individual,null] as $customer) {
            $html=Blade::render('<x-business-customer-icon :customer="$customer" />',compact('customer'));
            $this->assertSame($customer===$business,str_contains($html,'class="business-customer-icon"'));
        }
        $legacy=$business->only('id','name','business_name');
        $this->assertTrue(app(BusinessCustomerIcon::class)->isBusiness($legacy));
        $this->assertFalse(app(BusinessCustomerIcon::class)->isBusiness(['business_name'=>'Unlinked name']));
        $this->assertFalse(app(BusinessCustomerIcon::class)->isBusiness((object)['id'=>$business->id,'account_type'=>null]));
        $this->assertFalse(app(BusinessCustomerIcon::class)->isBusiness(new User(['id'=>$business->id])));
        $directory=$this->get(route('admin.customers.index'))->assertOk()->assertSee('Bakery &amp; &lt;Cafe&gt;',false);
        $dom=new \DOMDocument;@$dom->loadHTML($directory->getContent());$xpath=new \DOMXPath($dom);
        $this->assertSame(1,$xpath->query('//tr[.//strong[contains(.,"Bakery")]]//img[contains(@class,"business-customer-icon")]')->length);
        $this->assertSame(0,$xpath->query('//tr[.//strong[contains(.,"Individual Person")]]//img[contains(@class,"business-customer-icon")]')->length);
        $order=Order::create(['customer_id'=>$business->id,'checkout_token'=>Str::uuid(),'number'=>'ICON-ORDER','first_name'=>'Business','last_name'=>'Owner',
            'email'=>'icon@example.test','phone'=>'123','address'=>'Street','city'=>'Toronto','province'=>'ON','postal_code'=>'M4S','country'=>'Canada',
            'subtotal_cents'=>10000,'delivery_cents'=>0,'tax_cents'=>0,'currency'=>'CAD','payment_method'=>'cash','payment_status'=>'unpaid','status'=>'warehouse_pending','fulfillment'=>'delivery','ordered_at'=>now()]);
        $order->refresh();
        $before=$order->getRawOriginal();
        foreach ([route('admin.orders.index'),route('admin.orders.show',$order),route('admin.dashboard.balances')] as $url) {
            $this->get($url)->assertOk()->assertSee('class="business-customer-icon"',false);
        }
        $this->get(route('admin.customers.show', $business))->assertOk()
            ->assertSee('class="business-customer-icon"', false)
            ->assertSeeTextInOrder([$business->business_name, 'Owner: '.$business->name, $business->email, 'Customer ID']);
        $this->get(route('admin.customers.show', $individual))->assertOk()
            ->assertSeeTextInOrder([$individual->name, $individual->email, 'Customer ID'])
            ->assertDontSee('Owner: '.$individual->name);
        foreach (['financial'=>app(DocumentTheme::class)->order($order),'delivery-form'=>app(OrderDeliveryDocuments::class)->data($order),
            'delivery-envelope'=>app(OrderDeliveryDocuments::class)->data($order,true)] as $view=>$doc) {
            $html=view('documents.'.$view,['doc'=>$doc,'pdf'=>true])->render();
            $this->assertStringContainsString('class="business-customer-icon"',$html);
            $this->assertMatchesRegularExpression('/class="business-customer-icon" src="data:image\/png;base64,/',$html);
        }
        $this->assertSame($before,$order->fresh()->getRawOriginal());
        $this->get(route('admin.notifications.customers',['q'=>$business->email]))->assertOk()->assertJsonPath('results.0.account_type','business');
    }

    public function test_default_icon_is_small_and_used_when_custom_icon_is_unset_or_missing(): void
    {
        Storage::fake('local');
        $default=public_path(BusinessCustomerIcon::DEFAULT_ASSET);
        $this->assertFileExists($default);
        $this->assertSame($default,app(BusinessCustomerIcon::class)->filePath());
        $html=Blade::render('<x-business-customer-icon :business="true" />');
        $this->assertStringContainsString('width="14" height="14"',$html);
        $this->assertStringContainsString('data:image/png;base64,'.base64_encode(file_get_contents($default)),Blade::render('<x-business-customer-icon :business="true" :embedded="true" />'));
        GeneralSetting::find(1)->update(['business_customer_icon_path'=>'branding/business-customers/missing.png']);
        $this->get(route('site.business-customer-icon'))->assertOk();
        $this->assertSame($default,app(BusinessCustomerIcon::class)->filePath());
        $this->assertSame('',trim(Blade::render('<x-business-customer-icon :business="false" />')));
    }
}
