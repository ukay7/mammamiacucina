<?php

namespace Tests\Feature;

use App\Models\{GeneralSetting, PricingList, PricingListNumber, Product, Role, User};
use App\Services\{PricingLists, StaffPermissions};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Cache, DB};
use Illuminate\Support\Str;
use Tests\TestCase;

class PricingListTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        return User::factory()->create(['is_active'=>true,'role_id'=>Role::where('is_super',true)->value('id')]);
    }

    protected function product(array $overrides=[]): Product
    {
        return Product::create($overrides+['slug'=>(string)Str::uuid(),'category_id'=>1,'qr_code'=>(string)Str::uuid(),
            'premium_marketing_name'=>'Sample Pistachio Cake','is_active'=>true,'pieces_per_pack'=>6,'unit_weight_g'=>100,
            'total_selling_price_cad'=>'30.00','business_selling_price_cad'=>'24.00']);
    }

    protected function data(Product $product,array $overrides=[]): array
    {
        return $overrides+['title'=>'Cafe winter prices','pricing_tier'=>'business','effective_date'=>'2026-11-12',
            'valid_until'=>'2026-12-31','show_images'=>true,'recipient_name'=>'Example Cafe','notes'=>'Please confirm availability.',
            'filters'=>['q'=>'hidden search','categories'=>[],'sort'=>'name'],
            'items'=>[['product_id'=>$product->id,'price_basis'=>'box','box_price'=>'21.50','each_price'=>'999.99']]];
    }

    protected function saveList(array $data): PricingList
    {
        $token=$this->postJson(route('admin.pricing-lists.preview-new'),$data)->assertOk()->json('token');
        $this->postJson(route('admin.pricing-lists.store'),['preview_token'=>$token])->assertOk();
        return PricingList::latest('id')->firstOrFail();
    }

    public function test_preview_confirmation_and_pdf_leave_catalogue_and_financial_tables_unchanged(): void
    {
        $this->actingAs($this->admin()); $product=$this->product();
        GeneralSetting::find(1)->update(['email'=>'pricing@example.test']);
        $tables=['products','product_price_reviews','orders','order_items','quotations','customer_invoices','email_history'];
        $before=[]; foreach($tables as $table) $before[$table]=DB::table($table)->get()->toJson();
        $preview=$this->postJson(route('admin.pricing-lists.preview-new'),$this->data($product))->assertOk();
        $preview->assertHeader('Content-Type','application/json');
        $this->assertStringContainsString('no-transform',$preview->headers->get('Cache-Control'));
        $this->assertSame(2,substr_count($preview->json('html'),'<!--email_off-->pricing@example.test<!--/email_off-->'));
        $this->assertSame(0,PricingList::count());
        $this->assertStringContainsString('$21.50',$preview->json('html'));
        $this->assertStringContainsString('$3.58',$preview->json('html'));
        $this->assertStringNotContainsString('$999.99',$preview->json('html'));
        $this->postJson(route('admin.pricing-lists.store'),['preview_token'=>$preview->json('token'),'items'=>[]])->assertOk();
        $list=PricingList::firstOrFail();
        $document=$this->get(route('admin.pricing-lists.document',$list))->assertOk();
        $this->assertStringContainsString('no-transform',$document->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store',$document->headers->get('Cache-Control'));
        $this->assertSame(2,substr_count($document->getContent(),'<!--email_off-->pricing@example.test<!--/email_off-->'));
        $this->assertSame(2150,$list->items[0]['box_cents']);
        $this->assertSame(358,$list->items[0]['each_cents']);
        $this->assertSame('hidden search',$list->filters['q']);
        $pdf=$this->get(route('admin.pricing-lists.pdf',$list))->assertOk()->assertHeader('Content-Type','application/pdf');
        $this->assertStringStartsWith('%PDF-',$pdf->getContent());
        $this->assertSame(1,preg_match('/\/MediaBox\s*\[0\.000\s+0\.000\s+595\.280\s+841\.890\]/',$pdf->getContent()),'A4 page dimensions');
        $this->assertStringContainsString('no-store',$pdf->headers->get('Cache-Control'));
        foreach($tables as $table) $this->assertSame($before[$table],DB::table($table)->get()->toJson(),$table);
        // Network retries of the same confirmed preview do not create duplicates.
        $this->postJson(route('admin.pricing-lists.store'),['preview_token'=>$preview->json('token')])->assertOk();
        $this->assertSame(1,PricingList::count());
    }

    public function test_catalogue_tiers_fallback_pack_and_explicit_piece_basis(): void
    {
        $box=$this->product(['dna'=>['sale_unit'=>'piece']]);
        $piece=$this->product(['total_selling_price_cad'=>'1.44','business_selling_price_cad'=>null,'pieces_per_pack'=>56]);
        $piece->priceReviews()->create(['status'=>'saved','proposed_cents'=>144,'draft_revision'=>1,'sale_label'=>'piece','submitted_by'=>$this->admin()->id,'snapshot'=>['choice'=>'piece']]);
        $zero=$this->product(['business_selling_price_cad'=>0,'pieces_per_pack'=>null,'pack_weight_kg'=>3.5]);
        $missing=$this->product(['total_selling_price_cad'=>null,'business_selling_price_cad'=>null]);
        $rows=collect(app(PricingLists::class)->catalog())->keyBy('product_id');
        $this->assertSame(['each_cents'=>500,'box_cents'=>3000],$rows[$box->id]['defaults']['individual']);
        $this->assertSame(['each_cents'=>400,'box_cents'=>2400],$rows[$box->id]['defaults']['business']);
        $this->assertSame('6 x 100 g',$rows[$box->id]['pack']);
        $this->assertSame(['each_cents'=>144,'box_cents'=>8064],$rows[$piece->id]['defaults']['business']);
        $this->assertTrue($rows[$piece->id]['business_fallback']);
        $this->assertSame(['each_cents'=>null,'box_cents'=>0],$rows[$zero->id]['defaults']['business']);
        $this->assertSame('3.5 kg',$rows[$zero->id]['pack']);
        $this->assertSame(['each_cents'=>null,'box_cents'=>null],$rows[$missing->id]['defaults']['business']);
    }

    public function test_editor_saved_views_filters_duplicate_edit_and_delete(): void
    {
        $this->actingAs($this->admin()); $product=$this->product();
        $this->get(route('admin.pricing-lists.create'))->assertOk()->assertSee('Include product photos in PDF');
        $list=$this->saveList($this->data($product));
        foreach(['show','edit','document'] as $action) $this->get(route('admin.pricing-lists.'.$action,$list))->assertOk();
        $this->get(route('admin.pricing-lists.index',['search'=>$list->number,'tier'=>'business','from'=>'2026-11-01','to'=>'2026-12-01']))->assertOk()->assertSee('Cafe winter prices');
        $this->get(route('admin.pricing-lists.index',['tier'=>'individual']))->assertOk()->assertDontSee('Cafe winter prices');
        $this->get(route('admin.pricing-lists.index',['to'=>'2026-12-01']))->assertOk();
        $this->post(route('admin.pricing-lists.duplicate',$list))->assertRedirect();
        $copy=PricingList::latest('id')->firstOrFail();
        $this->assertNotEquals($list->id,$copy->id); $this->assertSame($list->items,$copy->items);
        $data=$this->data($product,['revision'=>1,'title'=>'Updated prices']); $data['items'][0]['box_price']='0.00';
        $preview=$this->postJson(route('admin.pricing-lists.preview',$copy),$data)->assertOk();
        $this->putJson(route('admin.pricing-lists.update',$copy),['preview_token'=>$preview->json('token')])->assertOk();
        $this->assertSame(0,$copy->fresh()->items[0]['box_cents']);
        $this->assertSame(2150,$list->fresh()->items[0]['box_cents']);
        $this->delete(route('admin.pricing-lists.destroy',$copy),['revision'=>1])->assertSessionHasErrors('revision');
        $this->delete(route('admin.pricing-lists.destroy',$copy),['revision'=>2])->assertRedirect();
        $this->assertSoftDeleted('pricing_lists',['id'=>$copy->id]);
        $this->get(route('admin.pricing-lists.pdf',$copy))->assertNotFound();
    }

    public function test_saved_product_company_and_recipient_snapshots_survive_catalogue_edits_and_deletion(): void
    {
        $this->actingAs($this->admin()); $product=$this->product();
        $customer=User::factory()->create(['account_type'=>'business'])->customerRecord(); $customer->update(['business_name'=>'Original Cafe']);
        GeneralSetting::find(1)->update(['gst_hst_number'=>'TEST-GST','email'=>'original@example.test']);
        $data=$this->data($product,['customer_id'=>$customer->id,'recipient_name'=>'Original Cafe']);
        $list=$this->saveList($data); $items=$list->items;
        $product->update(['premium_marketing_name'=>'Changed cake','business_selling_price_cad'=>99]);
        $customer->update(['business_name'=>'Changed Cafe']); GeneralSetting::find(1)->update(['email'=>'changed@example.test']);
        $html=$this->get(route('admin.pricing-lists.document',$list))->assertOk()->assertSee('Original Cafe')->assertSee('Sample Pistachio Cake')->assertSee('original@example.test')->assertDontSee('Changed cake')->getContent();
        $dom=new \DOMDocument; @$dom->loadHTML($html); $xpath=new \DOMXPath($dom);
        $this->assertSame(0,$xpath->query('//table[contains(@class,"doc-header")]//*[contains(text(),"GST/HST")]')->length);
        $this->assertSame(1,$xpath->query('//footer//div[contains(@class,"doc-registration")]')->length);
        $product->delete();
        $this->get(route('admin.pricing-lists.edit',$list))->assertOk();
        $preview=$this->postJson(route('admin.pricing-lists.preview',$list),$data+['revision'=>1])->assertOk();
        $this->putJson(route('admin.pricing-lists.update',$list),['preview_token'=>$preview->json('token')])->assertOk();
        $this->assertSame($items,$list->fresh()->items);
        $this->assertSame('Original Cafe',$list->fresh()->customer_snapshot['business_name']);
    }

    public function test_preview_token_is_bound_to_actor_list_and_revision_and_expires(): void
    {
        $admin=$this->admin(); $this->actingAs($admin); $p=$this->product(); $data=$this->data($p);
        $token=$this->postJson(route('admin.pricing-lists.preview-new'),$data)->assertOk()->json('token');
        $this->actingAs($this->admin())->postJson(route('admin.pricing-lists.store'),['preview_token'=>$token])->assertUnprocessable();
        $this->actingAs($admin); $list=$this->saveList($data);
        $this->putJson(route('admin.pricing-lists.update',$list),['preview_token'=>$token])->assertUnprocessable();
        $a=$this->postJson(route('admin.pricing-lists.preview',$list),$data+['revision'=>1])->assertOk()->json('token');
        $b=$this->postJson(route('admin.pricing-lists.preview',$list),$data+['revision'=>1])->assertOk()->json('token');
        $this->putJson(route('admin.pricing-lists.update',$list),['preview_token'=>$a])->assertOk();
        $this->putJson(route('admin.pricing-lists.update',$list),['preview_token'=>$b])->assertUnprocessable()->assertJsonValidationErrors('revision');
        $this->postJson(route('admin.pricing-lists.preview',$list),$data+['revision'=>1])->assertUnprocessable()->assertJsonValidationErrors('revision');
        $this->travel(21)->minutes();
        $this->postJson(route('admin.pricing-lists.store'),['preview_token'=>$token])->assertUnprocessable();
    }

    public function test_number_is_reserved_on_preview_and_retained_on_repreview_save_edit_and_pdf(): void
    {
        $this->actingAs($this->admin()); $product=$this->product();
        $data=$this->data($product,['draft_key'=>(string)Str::uuid()]);
        $first=$this->postJson(route('admin.pricing-lists.preview-new'),$data)->assertOk();
        $number=$first->json('number');
        $this->assertMatchesRegularExpression('/^PL-\d{5}$/',$number);
        $this->assertStringContainsString($number,$first->json('html'));
        $this->assertStringNotContainsString('>PREVIEW<',$first->json('html'));
        $this->assertSame(0,PricingList::count());
        $this->travel(21)->minutes();
        $second=$this->postJson(route('admin.pricing-lists.preview-new'),$data)->assertOk()->assertJsonPath('number',$number);
        $this->assertSame(1,PricingListNumber::count());
        // Another staff member can reserve a different list number before either saves.
        $admin=auth()->user(); $this->actingAs($this->admin());
        $other=$this->postJson(route('admin.pricing-lists.preview-new'),array_replace($data,['draft_key'=>(string)Str::uuid()]))->assertOk();
        $this->assertNotSame($number,$other->json('number'));
        $this->postJson(route('admin.pricing-lists.preview-new'),$data)->assertUnprocessable()->assertJsonValidationErrors('draft_key');
        // Save in reverse order: public reference must not be based on the row id.
        $this->postJson(route('admin.pricing-lists.store'),['preview_token'=>$other->json('token')])->assertOk();
        $this->actingAs($admin)->postJson(route('admin.pricing-lists.store'),['preview_token'=>$second->json('token')])->assertOk();
        $list=PricingList::where('number',$number)->firstOrFail();
        $this->assertSame($number,$list->number);
        $this->assertSame(2,$list->id);
        $this->get(route('admin.pricing-lists.index',['search'=>$number]))->assertOk()->assertSee($number)->assertDontSee($other->json('number'));
        $this->get(route('admin.pricing-lists.document',$list))->assertOk()->assertSee($number);
        $this->get(route('admin.pricing-lists.pdf',$list))->assertOk()->assertHeader('Content-Disposition','inline; filename="'.$number.'.pdf"');
        $edit=$this->postJson(route('admin.pricing-lists.preview',$list),$data+['revision'=>1])->assertOk()->assertJsonPath('number',$number);
        $this->putJson(route('admin.pricing-lists.update',$list),['preview_token'=>$edit->json('token')])->assertOk();
        $this->assertSame($number,$list->fresh()->number);
        $this->post(route('admin.pricing-lists.duplicate',$list))->assertRedirect();
        $copy=PricingList::latest('id')->firstOrFail();
        $this->assertNotSame($number,$copy->number);
        $this->assertSame(3,PricingListNumber::count());
    }

    public function test_same_draft_cannot_create_two_lists_and_deleted_numbers_are_not_reused(): void
    {
        $this->actingAs($this->admin()); $data=$this->data($this->product(),['draft_key'=>(string)Str::uuid()]);
        $first=$this->postJson(route('admin.pricing-lists.preview-new'),$data)->assertOk();
        $second=$this->postJson(route('admin.pricing-lists.preview-new'),$data)->assertOk();
        $this->assertSame($first->json('number'),$second->json('number'));
        $this->postJson(route('admin.pricing-lists.store'),['preview_token'=>$first->json('token')])->assertOk();
        $this->postJson(route('admin.pricing-lists.store'),['preview_token'=>$second->json('token')])->assertUnprocessable();
        $this->assertSame(1,PricingList::count());
        // Even if the response-cache update failed, retrying the confirmed token is safe.
        $key='pricing-list-preview:'.$first->json('token'); $cached=Cache::get($key); unset($cached['saved_id']); Cache::put($key,$cached,1200);
        $this->postJson(route('admin.pricing-lists.store'),['preview_token'=>$first->json('token')])->assertOk();
        $list=PricingList::firstOrFail();
        $this->delete(route('admin.pricing-lists.destroy',$list),['revision'=>1])->assertRedirect();
        $this->postJson(route('admin.pricing-lists.preview-new'),$data)->assertUnprocessable();
        $next=$this->postJson(route('admin.pricing-lists.preview-new'),array_replace($data,['draft_key'=>(string)Str::uuid()]))->assertOk();
        $this->assertNotSame($list->number,$next->json('number'));
    }

    public function test_number_migration_preserves_existing_and_deleted_list_references(): void
    {
        $this->actingAs($this->admin()); $product=$this->product();
        $list=$this->saveList($this->data($product)); $deleted=$this->saveList($this->data($product)); $deleted->delete();
        $migration=require database_path('migrations/2026_11_12_171000_add_pricing_list_numbers.php');
        $migration->down();
        $before=DB::table('pricing_lists')->orderBy('id')->get()->toJson();
        $migration->up();
        $columns=array_values(array_diff(\Illuminate\Support\Facades\Schema::getColumnListing('pricing_lists'),['number']));
        $this->assertSame($before,DB::table('pricing_lists')->select($columns)->orderBy('id')->get()->toJson());
        $this->assertSame('PL-00001',$list->fresh()->number);
        $this->assertSame('PL-00002',PricingList::withTrashed()->findOrFail($deleted->id)->number);
        $next=$this->postJson(route('admin.pricing-lists.preview-new'),$this->data($product))->assertOk();
        $this->assertSame('PL-00003',$next->json('number'));
    }

    public function test_invalid_selection_prices_dates_and_untrusted_metadata_are_rejected(): void
    {
        $this->actingAs($this->admin()); $p=$this->product(); $data=$this->data($p);
        foreach ([['items'=>[]],['valid_until'=>'2026-01-01'],['pricing_tier'=>'wholesale'],['effective_date'=>'yesterday'],['customer_id'=>999999]] as $invalid) {
            $this->postJson(route('admin.pricing-lists.preview-new'),array_replace($data,$invalid))->assertUnprocessable();
        }
        foreach (['-1','1.234','oops','1e2','1000001'] as $value) {
            $invalid=$data; $invalid['items'][0]['box_price']=$value;
            $this->postJson(route('admin.pricing-lists.preview-new'),$invalid)->assertUnprocessable();
        }
        $invalid=$data; $invalid['items'][]=$invalid['items'][0];
        $this->postJson(route('admin.pricing-lists.preview-new'),$invalid)->assertUnprocessable();
        $invalid=$data; $invalid['items'][0]['name']='Forged product';
        $this->postJson(route('admin.pricing-lists.preview-new'),$invalid)->assertUnprocessable();
        $invalid=$data; $invalid['items'][0]['product_id']=999999;
        $this->postJson(route('admin.pricing-lists.preview-new'),$invalid)->assertUnprocessable();
        $this->postJson(route('admin.pricing-lists.store'),$data)->assertUnprocessable();
        $this->assertSame(0,PricingList::count());
    }

    public function test_each_price_rounding_missing_prices_independent_unknown_packs_and_escaped_notes(): void
    {
        $this->actingAs($this->admin()); $p=$this->product(['pieces_per_pack'=>56]);
        $unknown=$this->product(['pieces_per_pack'=>null]);
        $data=$this->data($p,['notes'=>'<script>alert("bad")</script>','items'=>[
            ['product_id'=>$p->id,'price_basis'=>'each','each_price'=>'1.44','box_price'=>'0'],
            ['product_id'=>$unknown->id,'price_basis'=>'independent','each_price'=>'','box_price'=>'12.34']]]);
        $list=$this->saveList($data); $items=collect($list->items)->keyBy('product_id');
        $this->assertSame(8064,$items[$p->id]['box_cents']);
        $this->assertNull($items[$unknown->id]['each_cents']); $this->assertSame(1234,$items[$unknown->id]['box_cents']);
        $this->get(route('admin.pricing-lists.document',$list))->assertSee('&lt;script&gt;',false)->assertDontSee('<script>',false);
        $data['items'][0]['each_price']='1000000';
        $this->postJson(route('admin.pricing-lists.preview-new'),$data)->assertUnprocessable();
        $data['items'][0]['each_price']='';
        $list=$this->saveList($data); $this->assertNull(collect($list->items)->keyBy('product_id')[$p->id]['box_cents']);
    }

    public function test_permissions_separate_view_manage_and_customer_access(): void
    {
        $this->actingAs($this->admin()); $p=$this->product(); $list=$this->saveList($this->data($p));
        $role=Role::create(['name'=>'Price list reader','permissions'=>StaffPermissions::normalize(['access.admin.pricing-lists.index'])]);
        $reader=User::factory()->create(['is_active'=>true,'role_id'=>$role->id]);
        $this->actingAs($reader)->get(route('admin.pricing-lists.show',$list))->assertOk();
        $this->get(route('admin.pricing-lists.pdf',$list))->assertOk();
        $this->get(route('admin.pricing-lists.create'))->assertForbidden();
        $this->get(route('admin.pricing-lists.edit',$list))->assertForbidden();
        $this->postJson(route('admin.pricing-lists.preview-new'),$this->data($p))->assertForbidden();
        $this->post(route('admin.pricing-lists.duplicate',$list))->assertForbidden();
        $this->delete(route('admin.pricing-lists.destroy',$list),['revision'=>1])->assertForbidden();
        $role->update(['permissions'=>StaffPermissions::normalize(['pricing-lists.manage'])]);
        $this->actingAs($reader->fresh())->get(route('admin.pricing-lists.create'))->assertOk();
        $role->update(['permissions'=>StaffPermissions::normalize(['warehouse.pack'])]);
        $this->actingAs($reader->fresh())->get(route('admin.pricing-lists.index'))->assertForbidden();
        $customer=User::factory()->create(['account_type'=>'individual','is_active'=>true]);
        $this->actingAs($customer)->get(route('admin.pricing-lists.document',$list))->assertRedirect();
    }
}
