<?php

namespace Tests\Feature;

use App\Models\{Customer, Order, PosDraft, Product, Role, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Mail, Storage};
use Illuminate\Support\Str;
use Tests\TestCase;

class PosDraftTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Customer $customer;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');Mail::fake();
        $this->admin=User::factory()->create(['role_id'=>Role::where('is_super',true)->value('id'),'is_active'=>true]);
        $this->customer=User::factory()->create(['name'=>'Draft Buyer','account_type'=>'business','business_approved_at'=>now(),'phone'=>'555123','is_active'=>true])->customerRecord();
        $this->customer->update(['business_name'=>'Draft Bakery','address'=>'1 Main St','city'=>'Toronto','province'=>'ON','postal_code'=>'M5V 1A1','country'=>'Canada']);
        $this->product=Product::create(['category_id'=>1,'slug'=>'draft-cake','premium_marketing_name'=>'Draft Cake','qr_code'=>'DRAFT-CAKE','is_active'=>true,'total_selling_price_cad'=>25,'business_selling_price_cad'=>15]);
        $this->product->inventory()->create(['quantity_on_hand'=>10]);
        $this->actingAs($this->admin);
    }

    private function payload(): array
    {
        return ['customer_id'=>$this->customer->id,'items'=>[['id'=>$this->product->id,'quantity'=>2]],'fulfillment'=>'delivery','stage'=>'checkout','product_search'=>'Cake','product_category'=>1,
            'checkout'=>['phone'=>'555999','notes'=>'Side door, call first','payment_method'=>'etransfer','payment_status'=>'unpaid','delivery_service'=>''],
            'address'=>['type'=>'alternate','alternate'=>['address'=>'2 Warehouse St','city'=>'Toronto','province'=>'ON','postal_code'=>'M5V 2B2','country'=>'Canada']],
            'schedule'=>['date'=>'2026-12-20','time'=>'14:15','reference'=>1797794100]];
    }

    private function input(?array $payload=null): array
    {
        return ['client_key'=>(string)Str::uuid(),'revision'=>0,'payload'=>json_encode($payload??$this->payload())];
    }

    private function save(array $extra=[]): PosDraft
    {
        $id=$this->postJson(route('admin.pos.drafts.save'),$extra+$this->input())->assertOk()->json('id');
        return PosDraft::findOrFail($id);
    }

    private function quote(): string
    {
        return $this->postJson(route('admin.pos.quote'),['customer_id'=>$this->customer->id,'items'=>[['id'=>$this->product->id,'quantity'=>2]],'fulfillment'=>'pickup'])->assertOk()->json('quote');
    }

    private function complete(PosDraft $draft,string $quote)
    {
        return $this->postJson(route('admin.pos.store'),['quote'=>$quote,'customer_id'=>$this->customer->id,'payment_method'=>'cash','payment_status'=>'paid','pos_draft_id'=>$draft->id,'pos_draft_revision'=>$draft->revision]);
    }

    public function test_save_and_resume_preserve_checkout_and_private_receipt_without_placing_an_order(): void
    {
        $this->get(route('admin.pos.index'))->assertOk()->assertSee('id="open-pos-drafts"',false)->assertSee('Save draft & start new',false);
        $draft=$this->save(['receipt'=>UploadedFile::fake()->image('proof.png')]);
        $this->assertMatchesRegularExpression('/^DRAFT-\d{5}$/',$draft->number);
        $loaded=$this->getJson(route('admin.pos.drafts.show',$draft))->assertOk()->assertJsonPath('customer.business_name','Draft Bakery')
            ->assertJsonPath('payload.items.0.unit_cents',1500)->assertJsonPath('payload.items.0.quantity',2)
            ->assertJsonPath('payload.checkout.notes','Side door, call first')->assertJsonPath('payload.address.alternate.address','2 Warehouse St')
            ->assertJsonPath('payload.schedule.time','14:15')->assertJsonPath('payload.stage','checkout')->assertJsonPath('payload.product_category',1)->json();
        $this->assertEquals($this->payload()['checkout'],$loaded['payload']['checkout']);
        $this->get($loaded['receipt']['url'])->assertOk()->assertDownload('proof.png')->assertHeader('X-Content-Type-Options','nosniff');
        Storage::disk('local')->assertExists($draft->receipt_path);
        $this->assertNull($this->customer->fresh()->delivery_address);
        foreach(['orders','customer_invoices','payments','inventory_movements','order_settlements','email_history'] as $table)$this->assertDatabaseCount($table,0);
        $this->assertSame('10.000',$this->product->inventory()->first()->quantity_on_hand);
        Mail::assertNothingSent();Mail::assertNothingQueued();
        $this->assertSame('Draft Bakery · Draft Buyer',$this->getJson(route('admin.pos.drafts.index'))->assertOk()->json('rows.0.customer'));
    }

    public function test_save_retries_and_stale_changes_do_not_duplicate_or_overwrite_drafts_or_files(): void
    {
        $input=$this->input();
        $one=$this->postJson(route('admin.pos.drafts.save'),$input)->assertOk()->json();
        $this->postJson(route('admin.pos.drafts.save'),$input)->assertOk()->assertJsonPath('id',$one['id']);
        $this->assertDatabaseCount('pos_drafts',1);
        $draft=PosDraft::findOrFail($one['id']);$payload=$this->payload();$payload['items'][0]['quantity']=3;
        $update=['id'=>$draft->id,'revision'=>0,'client_key'=>$draft->client_key,'payload'=>json_encode($payload),'receipt'=>UploadedFile::fake()->image('new.png')];
        $this->postJson(route('admin.pos.drafts.save'),$update)->assertOk()->assertJsonPath('revision',1);
        $this->postJson(route('admin.pos.drafts.save'),$update)->assertOk()->assertJsonPath('revision',1);
        $file=$draft->fresh()->receipt_path;
        $this->postJson(route('admin.pos.drafts.save'),['id'=>$draft->id,'receipt'=>UploadedFile::fake()->image('stale.png')]+$input)->assertUnprocessable();
        $this->deleteJson(route('admin.pos.drafts.destroy',$draft),['revision'=>0])->assertUnprocessable();
        $this->assertSame(3,$draft->fresh()->snapshot['items'][0]['quantity']);
        $this->assertSame([$file],Storage::disk('local')->allFiles('pos-drafts'));
        $this->deleteJson(route('admin.pos.drafts.destroy',$draft),['revision'=>1])->assertOk();
        $this->getJson(route('admin.pos.drafts.show',$draft))->assertNotFound();
        $this->getJson(route('admin.pos.drafts.index'))->assertOk()->assertJsonPath('total',0);
        $this->postJson(route('admin.pos.drafts.save'),$input)->assertUnprocessable();
    }

    public function test_only_super_admin_can_use_any_draft_endpoint_or_convert_one(): void
    {
        $draft=$this->save(['receipt'=>UploadedFile::fake()->image('private.png')]);
        $role=Role::create(['name'=>'Counter staff','permissions'=>['pos.manage']]);
        $staff=User::factory()->create(['role_id'=>$role->id,'is_active'=>true]);
        $this->actingAs($staff)->get(route('admin.pos.index'))->assertOk()->assertDontSee('id="open-pos-drafts"',false)->assertDontSee('data-save-pos-draft',false);
        $this->getJson(route('admin.pos.drafts.index'))->assertForbidden();
        $this->postJson(route('admin.pos.drafts.save'),$this->input())->assertForbidden();
        $this->getJson(route('admin.pos.drafts.show',$draft))->assertForbidden();
        $this->getJson(route('admin.pos.drafts.receipt',$draft))->assertForbidden();
        $this->deleteJson(route('admin.pos.drafts.destroy',$draft),['revision'=>0])->assertForbidden();
        $this->complete($draft,$this->quote())->assertForbidden();
        $this->assertDatabaseCount('orders',0);
        $this->actingAs($this->customer->user)->getJson(route('admin.pos.drafts.index'))->assertRedirect(route('customer.orders'));
        $this->admin->update(['is_active'=>false]);
        $this->actingAs($this->admin)->getJson(route('admin.pos.drafts.index'))->assertRedirect(route('admin.login'));
    }

    public function test_successful_conversion_is_atomic_and_retries_do_not_place_the_draft_twice(): void
    {
        $draft=$this->save();$firstQuote=$this->quote();$secondQuote=$this->quote();
        $number=$this->complete($draft,$firstQuote)->assertOk()->json('number');
        $order=Order::firstOrFail();
        $this->assertSame($order->id,$draft->fresh()->order_id);
        $this->assertSame('8.000',$this->product->inventory()->first()->quantity_on_hand);
        $this->complete($draft,$firstQuote)->assertOk()->assertJsonPath('number',$number);
        $this->complete($draft,$secondQuote)->assertUnprocessable();
        $this->getJson(route('admin.pos.drafts.index'))->assertOk()->assertJsonPath('total',0);
        $this->getJson(route('admin.pos.drafts.show',$draft))->assertUnprocessable();
        $this->assertDatabaseCount('orders',1);$this->assertDatabaseCount('inventory_movements',1);
        $this->assertSame('paid',$order->payment_status);$this->assertSame(0,$order->balance_cents);
    }

    public function test_changed_prices_and_stale_draft_revisions_prevent_checkout_and_leave_the_draft_available(): void
    {
        $draft=$this->save();$quote=$this->quote();
        $this->product->update(['business_selling_price_cad'=>17]);
        $this->complete($draft,$quote)->assertUnprocessable();
        $this->assertNull($draft->fresh()->order_id);
        $this->assertSame('10.000',$this->product->inventory()->first()->quantity_on_hand);
        $this->assertDatabaseCount('orders',0);
        $newQuote=$this->quote();
        $this->save(['id'=>$draft->id,'client_key'=>$draft->client_key,'revision'=>0]);
        $this->complete($draft,$newQuote)->assertUnprocessable();
        $this->complete($draft->fresh(),$newQuote)->assertOk()->assertJsonPath('total',3400);
    }

    public function test_invalid_data_and_unsupported_receipts_are_rejected(): void
    {
        foreach ([['items'=>[]],['customer_id'=>999999],['items'=>[['id'=>$this->product->id,'quantity'=>10000]]],['items'=>[['id'=>$this->product->id,'quantity'=>1,'unit_cents'=>1]]]] as $invalid) {
            $this->postJson(route('admin.pos.drafts.save'),$this->input($invalid+$this->payload()))->assertUnprocessable();
        }
        foreach ([UploadedFile::fake()->image('huge.png')->size(5121),UploadedFile::fake()->createWithContent('script.svg','<svg><script>alert(1)</script></svg>')] as $receipt) {
            $this->postJson(route('admin.pos.drafts.save'),['receipt'=>$receipt]+$this->input())->assertUnprocessable();
        }
        $this->assertDatabaseCount('pos_drafts',0);$this->assertEmpty(Storage::disk('local')->allFiles('pos-drafts'));
    }

    public function test_incomplete_checkout_can_be_saved_and_receipt_replacement_removal_is_consistent(): void
    {
        $payload=$this->payload();$payload['stage']='cart';$payload['checkout']['phone']='';$payload['address']['alternate']=[];
        $payload['schedule']=['date'=>'','time'=>'','reference'=>''];
        $draft=$this->save(['payload'=>json_encode($payload),'receipt'=>UploadedFile::fake()->image('first.png')]);$first=$draft->receipt_path;
        $this->save(['id'=>$draft->id,'client_key'=>$draft->client_key,'revision'=>0,'receipt'=>UploadedFile::fake()->image('second.png')]);
        Storage::disk('local')->assertMissing($first);$second=$draft->fresh()->receipt_path;Storage::disk('local')->assertExists($second);
        $this->save(['id'=>$draft->id,'client_key'=>$draft->client_key,'revision'=>1,'remove_receipt'=>1]);
        $this->assertNull($draft->fresh()->receipt_path);Storage::disk('local')->assertMissing($second);
    }

    public function test_failure_after_order_creation_rolls_back_conversion_and_inventory(): void
    {
        $draft=$this->save();$quote=$this->quote();
        $this->partialMock(\App\Services\CustomerEmails::class)->shouldReceive('order')->once()->andThrow(new \RuntimeException('Simulated outbox failure'));
        $this->complete($draft,$quote)->assertStatus(500);
        $this->assertNull($draft->fresh()->order_id);$this->assertSame(0,$draft->fresh()->revision);
        $this->assertDatabaseCount('orders',0);$this->assertDatabaseCount('inventory_movements',0);
        $this->assertSame('10.000',$this->product->inventory()->first()->quantity_on_hand);
    }

    public function test_listing_search_pagination_and_shared_super_admin_access_do_not_sort_large_snapshots(): void
    {
        $draft=$this->save();
        for($i=0;$i<21;$i++)$draft->replicate()->fill(['client_key'=>(string)Str::uuid(),'customer_label'=>'Other Bakery '.$i])->save();
        $other=User::factory()->create(['role_id'=>$this->admin->role_id,'is_active'=>true]);$this->actingAs($other);
        DB::enableQueryLog();
        $this->getJson(route('admin.pos.drafts.index'))->assertOk()->assertJsonPath('total',22)->assertJsonCount(20,'rows');
        $queries=DB::getQueryLog();DB::disableQueryLog();
        $listing=collect($queries)->first(fn($q)=>str_contains($q['query'],'pos_drafts')&&str_contains($q['query'],'order by'))['query'];
        $this->assertStringNotContainsString('snapshot',$listing);$this->assertStringNotContainsString('select *',$listing);
        $this->getJson(route('admin.pos.drafts.index',['page'=>2]))->assertOk()->assertJsonCount(2,'rows');
        $this->getJson(route('admin.pos.drafts.index',['q'=>'Draft Bakery']))->assertOk()->assertJsonCount(1,'rows');
        $this->getJson(route('admin.pos.drafts.index',['q'=>$draft->number]))->assertOk()->assertJsonPath('rows.0.id',$draft->id);
        $this->getJson(route('admin.pos.drafts.show',$draft))->assertOk();
    }
}
