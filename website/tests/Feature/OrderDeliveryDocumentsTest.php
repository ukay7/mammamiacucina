<?php

namespace Tests\Feature;

use App\Models\{GeneralSetting, Order, Product, Role, User};
use App\Services\{CustomerInvoicing, OrderDeliveryDocuments};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\{Carbon, Str};
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrderDeliveryDocumentsTest extends TestCase
{
    use RefreshDatabase;

    protected function fixture(): array
    {
        $this->travelTo(Carbon::parse('2026-11-12 16:00:00', 'UTC'));
        GeneralSetting::findOrFail(1)->update(['document_company_name'=>'Mamma Mia Cucina INC.',
            'email'=>'dispatch@example.test','phone'=>'+1 416 555 0100','mailing_address'=>'10 Sample Company Office, Toronto, ON',
            'document_warehouse_address'=>'20 Sample Company Warehouse, Vaughan, ON','gst_hst_number'=>'TEST-REGISTRATION']);
        $admin=User::factory()->create(['is_active'=>true,'role_id'=>Role::where('is_super',true)->value('id')]);
        $user=User::factory()->create(['name'=>'Alex Receiver','email'=>'receiver@example.test','account_type'=>'business',
            'is_active'=>true,'email_verified_at'=>now(),'role_id'=>Role::where('name','Customer')->value('id')]);
        $customer=$user->customerRecord();
        $customer->update(['account_type'=>'business','business_name'=>'Sample Bakery & Cafe',
            'address'=>'100 Customer Head Office','city'=>'Markham','province'=>'ON','postal_code'=>'L3P 1A1','country'=>'Canada']);
        $product=Product::create(['category_id'=>1,'slug'=>Str::uuid(),'premium_marketing_name'=>'Current catalogue cake',
            'qr_code'=>Str::uuid(),'is_active'=>true,'size_diameter_cm'=>'18','unit_weight_g'=>85,'pieces_per_pack'=>12,
            'pack_weight_kg'=>1.02,'uom'=>'Pack','total_selling_price_cad'=>123.45,'business_selling_price_cad'=>98.76]);
        $order=Order::create(['checkout_token'=>Str::uuid(),'number'=>'MMC-DELIVERY-QA','source'=>'website','customer_id'=>$customer->id,
            'first_name'=>'Alex','last_name'=>'Receiver','email'=>'order-contact@example.test','phone'=>'+1 416 555 0123',
            'address'=>'975 Sample Receiving Road, Unit 4','city'=>'Woodbridge','province'=>'ON','postal_code'=>'L4H 2Z7','country'=>'Canada',
            'fulfillment'=>'delivery','delivery_scheduled_at'=>'2026-11-14 14:45:00','delivery_service'=>'bullet','delivery_service_name'=>'Bullet',
            'delivery_from_postal'=>'M2N','delivery_to_postal'=>'L4H 2Z7','delivery_from_zone'=>8,'delivery_to_zone'=>3,'delivery_rate_cents'=>5876,
            'subtotal_cents'=>24690,'delivery_cents'=>5876,'tax_cents'=>3210,'currency'=>'CAD','payment_method'=>'credit',
            'payment_status'=>'unpaid','status'=>'parked_admin','notes'=>'Please use the side receiving entrance.','ordered_at'=>now()]);
        $order->items()->create(['product_id'=>$product->id,'name'=>'Saved order cake','product_code'=>'SUP-1','qr_code'=>'MMC-CAKE-18',
            'quantity'=>2,'unit_cents'=>12345,'line_cents'=>24690,'original_unit_cents'=>15000,'discount_bps'=>1770]);
        $this->actingAs($admin);
        return compact('order','customer','product','admin','user');
    }

    public function test_buttons_are_available_on_pending_completed_and_detail_views(): void
    {
        $f=$this->fixture(); $order=$f['order'];
        foreach (['parked_admin'=>'admin.orders.index','completed'=>'admin.orders.completed'] as $status=>$listing) {
            $order->updateQuietly(['status'=>$status]);
            foreach ([route($listing),route('admin.orders.show',$order)] as $url) {
                $response=$this->get($url)->assertOk();
                foreach (['delivery-print','delivery-form'] as $kind) $response->assertSee(route('admin.orders.'.$kind,$order), false);
            }
        }
    }

    public function test_form_contains_delivery_details_and_sizes_without_financial_fields(): void
    {
        $f=$this->fixture();
        $response=$this->get(route('admin.orders.delivery-form',$f['order']))->assertOk();
        foreach (['Sample Bakery &amp; Cafe','Alex Receiver','Customer account #'.$f['customer']->id,
            '100 Customer Head Office','975 Sample Receiving Road, Unit 4','order-contact@example.test','+1 416 555 0123',
            '14 Nov 2026, 9:45 AM EST','Bullet','M2N (Zone 8)','Saved order cake','MMC-CAKE-18','18 cm','85 g / piece',
            '12 pieces / pack','1.02 kg / pack','Receiver name','Receiver signature','side receiving entrance'] as $text) {
            $response->assertSee($text, false);
        }
        $text=html_entity_decode(strip_tags($response->getContent()));
        foreach (['$','CAD','123.45','246.90','58.76','32.10','150.00','17.7%',
            'Account summary','Unit price','Line total','Subtotal','Balance due','Payment instructions','Saved route rate'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden,$text);
        }
        $this->assertStringContainsString('no-store',$response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private',$response->headers->get('Cache-Control'));
        $dom=new \DOMDocument; @$dom->loadHTML($response->getContent()); $xpath=new \DOMXPath($dom);
        $this->assertSame(0,$xpath->query('//table[contains(@class,"doc-header")]//*[contains(text(),"GST/HST")]')->length);
        $this->assertSame(1,$xpath->query('//footer//div[contains(@class,"doc-registration")]')->length);
        $this->assertSame('2',trim($xpath->query('//tbody/tr/td[last()]')->item(0)->textContent));
    }

    public function test_envelope_keeps_order_destination_when_profile_addresses_change(): void
    {
        $f=$this->fixture();
        $f['customer']->update(['address'=>'Changed company office','delivery_address'=>['address'=>'Changed next delivery']]);
        $response=$this->get(route('admin.orders.delivery-print',$f['order']))->assertOk();
        foreach (['Deliver to','975 Sample Receiving Road, Unit 4','Sample Bakery &amp; Cafe','MMC-DELIVERY-QA',
            '14 Nov 2026, 9:45 AM EST','241.3mm 104.775mm','Made in Italy.','Finished by you.'] as $text) $response->assertSee($text,false);
        foreach (['Changed company office','Changed next delivery','100 Customer Head Office','Bill to','123.45'] as $text) $response->assertDontSee($text);
    }

    public function test_pdf_downloads_have_exact_paper_sizes_and_do_not_change_order_data(): void
    {
        $f=$this->fixture(); $order=$f['order']; $order->updateQuietly(['status'=>'completed']);
        app(CustomerInvoicing::class)->issue($order);
        $tables=['orders','order_items','customer_invoices','order_events','order_settlements','email_history','inventory_movements'];
        $snapshot=fn()=>collect($tables)->mapWithKeys(fn($table)=>[$table=>DB::table($table)->orderBy('id')->get()->toJson()])->all();
        $before=$snapshot();
        foreach (['delivery-print'=>[684,297],'delivery-form'=>[595.28,841.89]] as $kind=>$size) {
            $response=$this->get(route('admin.orders.'.$kind,[$order,'download'=>1]))->assertOk()->assertHeader('Content-Type','application/pdf');
            $bytes=$response->getContent();
            $this->assertStringStartsWith('%PDF-',$bytes);
            $this->assertStringContainsString('attachment;',$response->headers->get('Content-Disposition'));
            $this->assertSame(1,preg_match('/\/MediaBox\s*\[\s*0(?:\.0+)?\s+0(?:\.0+)?\s+([\d.]+)\s+([\d.]+)/',$bytes,$box));
            $this->assertEqualsWithDelta($size[0],(float)$box[1],0.1);
            $this->assertEqualsWithDelta($size[1],(float)$box[2],0.1);
            $this->assertSame(1,preg_match_all('/\/Type\s*\/Page\b/',$bytes));
        }
        $this->assertSame($before,$snapshot());
    }

    public function test_access_follows_admin_permissions_warehouse_scope_and_deleted_orders(): void
    {
        $f=$this->fixture(); $order=$f['order'];
        $restricted=User::factory()->create(['role_id'=>Role::create(['name'=>'Delivery QA restricted','permissions'=>['dashboard.view']])->id,'is_active'=>true]);
        $warehouse=User::factory()->create(['role_id'=>Role::create(['name'=>'Delivery QA warehouse','permissions'=>['warehouse.pack']])->id,'is_active'=>true]);
        foreach (['delivery-print','delivery-form'] as $kind) {
            $url=route('admin.orders.'.$kind,$order);
            $this->actingAs($f['user'])->get($url)->assertRedirect(route('customer.orders'));
            $this->actingAs($restricted)->get($url)->assertForbidden();
            $this->actingAs($warehouse)->get($url)->assertNotFound();
            $this->get($url.'?download=1')->assertNotFound();
            $order->updateQuietly(['status'=>'warehouse_pending']);
            $this->get($url)->assertOk();
            $this->get(route('admin.orders.index'))->assertOk()->assertSee($url,false);
            $order->updateQuietly(['status'=>'completed','source'=>'pos','created_by'=>$warehouse->id]);
            $this->get($url)->assertOk();
            $this->get(route('admin.orders.completed'))->assertOk()->assertSee($url,false);
            $order->updateQuietly(['status'=>'parked_admin','source'=>'website','created_by'=>null]);
        }
        $this->actingAs($f['admin']); $order->delete();
        foreach (['delivery-print','delivery-form'] as $kind) $this->get(route('admin.orders.'.$kind,$order))->assertNotFound();
    }

    public function test_long_envelopes_stay_on_one_page_and_large_forms_keep_all_items(): void
    {
        $f=$this->fixture(); $order=$f['order'];
        $f['customer']->update(['business_name'=>'Sample Bakery, Patisserie and Wholesale Food Distribution Company']);
        $order->updateQuietly(['first_name'=>'Alexandria-Catherine','last_name'=>'Sample Receiver',
            'address'=>'975 Sample Receiving Road, Building B, Unit 409, North Warehouse Receiving Entrance',
            'email'=>'warehouse.receiving.department@example.test']);
        $response=$this->get(route('admin.orders.delivery-print',[$order,'download'=>1]))->assertOk();
        $this->assertSame(1,preg_match_all('/\/Type\s*\/Page\b/',$response->getContent()));

        \Illuminate\Support\Facades\Storage::fake('local');
        $image=\Illuminate\Http\UploadedFile::fake()->image('cake.png',320,240)->store('product-media','local');
        $f['product']->media()->create(['kind'=>'image','mime_type'=>'image/png','path'=>$image,'original_name'=>'cake.png','sort_order'=>0]);
        for ($i=2;$i<=30;$i++) $order->items()->create(['product_id'=>$f['product']->id,'name'=>'Delivery item '.$i,
            'product_code'=>'ITEM-'.$i,'quantity'=>3,'unit_cents'=>99999,'line_cents'=>299997]);
        $html=$this->get(route('admin.orders.delivery-form',$order))->assertOk()->assertSee('data:image/jpeg;base64,',false)->getContent();
        $this->assertSame(1,substr_count($html,'Receiver signature'));
        $this->assertLessThan(strpos($html,'Receiver signature'),strpos($html,'Delivery item 30'));
        $this->assertStringNotContainsString('999.99',$html);
        $pdf=$this->get(route('admin.orders.delivery-form',[$order,'download'=>1]))->assertOk();
        $this->assertGreaterThan(1,preg_match_all('/\/Type\s*\/Page\b/',$pdf->getContent()));
    }

    public function test_pickup_guests_missing_products_and_escaping_are_supported(): void
    {
        $f=$this->fixture(); $order=$f['order'];
        $order->updateQuietly(['customer_id'=>null,'fulfillment'=>'pickup','pickup_address'=>'Saved pickup counter','first_name'=>'<script>alert(1)</script>']);
        $order->items()->update(['product_id'=>null]);
        foreach (['delivery-print','delivery-form'] as $kind) {
            $response=$this->get(route('admin.orders.'.$kind,$order))->assertOk()->assertSee('Saved pickup counter')->assertSee('Pick up at');
            $response->assertDontSee('<script>alert(1)</script>',false)->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;',false);
            $response->assertDontSee('14 Nov 2026, 9:45 AM EST')->assertDontSee('Courier:')->assertDontSee('18 cm');
            $this->get(route('admin.orders.'.$kind,[$order,'download'=>'invalid']))->assertSessionHasErrors('download');
        }
        $this->get(route('admin.orders.delivery-form',$order))->assertSee('Saved order cake')->assertSee('MMC-CAKE-18');
    }
}
