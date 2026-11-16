<?php

namespace Tests\Feature;

use App\Models\{Category, Product, Role, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PosProductBrowserTest extends TestCase
{
    use RefreshDatabase;

    private function fixtures(): array
    {
        $staff=User::factory()->create(['role_id'=>Role::create(['name'=>'POS only','permissions'=>['pos.manage']])->id,'is_active'=>true]);
        $buyer=User::factory()->create(['account_type'=>'business','business_approved_at'=>now(),'is_active'=>true])->customerRecord();
        $product=Product::create(['category_id'=>1,'slug'=>'browser-cake','premium_marketing_name'=>'Browser Cake','qr_code'=>'001234','product_code'=>'CAKE-01',
            'is_active'=>true,'total_selling_price_cad'=>25,'business_selling_price_cad'=>15,'supplier_unit_price_eur'=>2,
            'pack_weight_kg'=>2.5,'pieces_per_pack'=>12,'dna'=>['net_carton_kg'=>5,'pieces_per_carton'=>24]]);
        $this->actingAs($staff);
        return [$staff,$buyer,$product];
    }

    public function test_category_browsing_includes_secondary_categories_and_all_paginated_products(): void
    {
        [$staff,$buyer,$product]=$this->fixtures();
        $category=Category::create(['name'=>'Cakes & Pastries','slug'=>'cakes-pastries','is_active'=>true]);
        $product->categories()->attach($category);
        for($i=0;$i<22;$i++)Product::create(['category_id'=>$category->id,'slug'=>'browser-'.$i,'premium_marketing_name'=>'Browser Cake '.str_pad($i,2,'0',STR_PAD_LEFT),'is_active'=>true]);
        Product::create(['category_id'=>1,'slug'=>'other-product','premium_marketing_name'=>'Other Product']);
        $filters=['customer_id'=>$buyer->id,'category_id'=>$category->id];
        $one=$this->getJson(route('admin.pos.products',$filters))->assertOk()->assertJsonPath('total',23)->assertJsonPath('next_page',2)->assertJsonCount(20,'products')->json('products');
        $two=$this->getJson(route('admin.pos.products',$filters+['page'=>2]))->assertOk()->assertJsonPath('next_page',null)->assertJsonCount(3,'products')->json('products');
        $this->assertCount(23,array_unique(array_column(array_merge($one,$two),'id')));
        $this->getJson(route('admin.pos.products',$filters+['q'=>'Cake 21']))->assertOk()->assertJsonPath('total',1)->assertJsonPath('products.0.name','Browser Cake 21');
        $this->get(route('admin.pos.index'))->assertOk()->assertSee('Cakes &amp; Pastries',false)->assertSee('id="pos-product-search-form"',false)->assertDontSee('id="scan-code"',false);
    }

    public function test_scans_cross_category_filters_and_preserve_barcode_leading_zeros(): void
    {
        [$staff,$buyer,$product]=$this->fixtures();
        $other=Category::create(['name'=>'Other category','slug'=>'other','is_active'=>true]);
        $this->getJson(route('admin.pos.products',['customer_id'=>$buyer->id,'category_id'=>$other->id,'q'=>'001234','scan'=>1]))
            ->assertOk()->assertJsonPath('total',1)->assertJsonPath('products.0.id',$product->id)->assertJsonPath('products.0.barcode','001234');
        $this->getJson(route('admin.pos.products',['customer_id'=>$buyer->id,'category_id'=>999999]))->assertUnprocessable();
    }

    public function test_details_use_customer_prices_and_expose_product_information_without_supplier_costs(): void
    {
        [$staff,$buyer,$product]=$this->fixtures();
        DB::table('customer_product_discounts')->insert(['customer_id'=>$buyer->id,'product_id'=>$product->id,'enabled'=>true,'discount_bps'=>1000]);
        $url=route('admin.pos.product',[$product,'customer_id'=>$buyer->id]);
        $data=$this->getJson($url)->assertOk()->assertJsonPath('product.unit_cents',1350)
            ->assertJsonPath('details.Net carton weight (kg)','5')->assertJsonPath('details.Pieces per carton','24')
            ->assertJsonPath('details.Pack Weight (kg)','2.5')->assertJsonPath('product.pack','24 pcs / carton · 2.5 kg / pack')->json();
        $this->assertArrayNotHasKey('Supplier Unit Price (EUR)',$data['details']);$this->assertStringNotContainsString('supplier_unit_price',json_encode($data));
        $this->getJson(route('admin.pos.products',['customer_id'=>$buyer->id]))->assertOk()->assertJsonPath('products.0.unit_cents',1350);
        $this->postJson(route('admin.pos.quote'),['customer_id'=>$buyer->id,'items'=>[['id'=>$product->id,'quantity'=>3]],'fulfillment'=>'pickup'])->assertOk()->assertJsonPath('subtotal',4050);
        $this->getJson(route('admin.pos.product',$product))->assertUnprocessable();
        $staff->role->update(['permissions'=>['orders.view']]);$staff->unsetRelation('role');
        $this->getJson($url)->assertForbidden();
    }
}
