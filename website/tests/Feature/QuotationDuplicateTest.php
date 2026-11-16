<?php

namespace Tests\Feature;

use App\Models\{Product, Quotation, Role, User};
use App\Services\DocumentProductImages;
use Dompdf\{Dompdf, Options};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QuotationDuplicateTest extends TestCase
{
    use RefreshDatabase;

    private function source(): array
    {
        $admin = User::factory()->create(['is_active'=>true,'role_id'=>Role::where('is_super',true)->value('id')]);
        $this->actingAs($admin);
        $customer = User::factory()->create(['account_type'=>'business'])->customerRecord();
        $customer->update(['business_name'=>'Original Company']);
        $product = Product::create(['category_id'=>1,'slug'=>'duplicate-pastry','premium_marketing_name'=>'Saved pastry',
            'qr_code'=>'00123','is_active'=>true,'total_selling_price_cad'=>100,'business_selling_price_cad'=>80,'dna'=>['pieces_per_carton'=>6]]);
        $this->post(route('admin.quotations.store'), ['customer_id'=>$customer->id,'due_date'=>'2026-01-01','pricing_tier'=>'business',
            'items'=>[['product_id'=>$product->id,'quantity'=>3,'unit_price'=>'22.0320','discount_percent'=>10]],
            'discount_percent'=>5,'charge_percent'=>2,'tax_percent'=>13,'payment_terms'=>'Net 15 days','delivery_terms'=>'Customer pickup','notes'=>'Saved quotation notes'])
            ->assertSessionHasNoErrors();

        return [Quotation::firstOrFail(), $product, $admin];
    }

    public function test_copy_keeps_the_exact_offer_with_a_new_recipient_number_and_issue_date(): void
    {
        [$source, $product] = $this->source();
        $source->update(['revision'=>4,'created_at'=>now()->subYear(),'due_date'=>\App\Support\BusinessTime::today()->subDay()]);
        $before = $source->fresh()->getAttributes();
        $product->update(['premium_marketing_name'=>'New catalogue name','business_selling_price_cad'=>999,'is_active'=>false]);
        $catalogue = $product->fresh()->getAttributes();
        $recipient = User::factory()->create(['account_type'=>'business'])->customerRecord();
        $recipient->update(['business_name'=>'New Company','address'=>'Another company address']);
        $creator = User::factory()->create(['is_active'=>true,'role_id'=>Role::where('is_super',true)->value('id')]);
        $this->actingAs($creator);
        $form = $this->get(route('admin.quotations.duplicate',$source))->assertOk()->assertSee('New Company');
        $this->assertSame(\App\Support\BusinessTime::today()->addDays(30)->toDateString(), $form->viewData('dueDate'));
        $this->assertDatabaseCount('quotations',1);
        $this->post(route('admin.quotations.duplicate.store',$source), ['customer_id'=>$recipient->id,'due_date'=>'2027-01-20','revision'=>4,
            'items'=>[], 'total_cents'=>1, 'created_by'=>1])->assertSessionHasNoErrors();
        $copy = Quotation::latest('id')->firstOrFail();
        $this->assertNotSame($source->number,$copy->number);
        $this->assertEquals($creator->id,$copy->created_by);
        $this->assertEquals(1,$copy->revision);
        $this->assertTrue($copy->created_at->isSameDay(now()));
        $this->assertSame('2027-01-20',$copy->due_date->toDateString());
        $this->assertSame('New Company',$copy->customer_snapshot['business_name']);
        $this->assertSame($recipient->email,$copy->customer_snapshot['email']);
        $this->assertSame('Another company address',$copy->customer_snapshot['address']);
        foreach (['items','pricing_tier','subtotal_cents','line_discount_cents','discount_cents','discount_basis_points',
            'charge_cents','charge_basis_points','tax_cents','tax_basis_points','total_cents','payment_terms','delivery_terms','notes'] as $field) {
            $this->assertEquals($source->$field,$copy->$field,$field);
        }
        $this->get(route('admin.quotations.edit',$copy))->assertOk()->assertSee('New Company');
        $copy->update(['notes'=>'Only the copy changes']);
        $this->assertSame($before,$source->fresh()->getAttributes());
        $this->assertSame($catalogue,$product->fresh()->getAttributes());
        $this->assertDatabaseCount('orders',0);
        $this->assertDatabaseCount('payments',0);
        $this->assertDatabaseCount(\App\Models\EmailHistory::class,0);
    }

    public function test_fixed_discount_legacy_quotes_are_copied_without_recalculation(): void
    {
        [$source] = $this->source();
        $source->update(['discount_basis_points'=>null,'discount_cents'=>137,'total_cents'=>5432]);
        $this->post(route('admin.quotations.duplicate.store',$source), ['customer_id'=>$source->customer_id,'due_date'=>'2027-02-01','revision'=>1])->assertSessionHasNoErrors();
        $copy = Quotation::latest('id')->first();
        $this->assertNull($copy->discount_basis_points);
        $this->assertEquals(137,$copy->discount_cents);
        $this->assertEquals(5432,$copy->total_cents);
        $this->assertSame($source->items,$copy->items);
    }

    public function test_permissions_validation_and_stale_source_prevent_unwanted_copies(): void
    {
        [$source, , $admin] = $this->source();
        $this->get(route('admin.quotations.index'))->assertOk()->assertSee(route('admin.quotations.duplicate',$source),false);
        $this->get(route('admin.quotations.show',$source))->assertOk()->assertSee('Duplicate');
        $staff = User::factory()->create(['is_active'=>true,'role_id'=>Role::create(['name'=>'Quote viewer','permissions'=>['quotations.view']])->id]);
        $this->actingAs($staff)->get(route('admin.quotations.show',$source))->assertOk()->assertDontSee(route('admin.quotations.duplicate',$source),false);
        $this->get(route('admin.quotations.duplicate',$source))->assertForbidden();
        $this->post(route('admin.quotations.duplicate.store',$source),[])->assertForbidden();
        $this->actingAs($admin);
        $this->postJson(route('admin.quotations.duplicate.store',$source),[])->assertUnprocessable()->assertJsonValidationErrors(['customer_id','due_date','revision']);
        $payload=['customer_id'=>$source->customer_id,'due_date'=>'2027-02-01','revision'=>1];
        $this->postJson(route('admin.quotations.duplicate.store',$source),array_replace($payload,['customer_id'=>999999]))->assertUnprocessable();
        $source->update(['revision'=>2]);
        $this->postJson(route('admin.quotations.duplicate.store',$source),$payload)->assertUnprocessable()->assertJsonValidationErrors('revision');
        $this->get(route('admin.quotations.duplicate',999999))->assertNotFound();
        $this->assertDatabaseCount('quotations',1);
    }

    public function test_view_and_pdf_embed_small_product_photos_and_handle_missing_files(): void
    {
        Storage::fake('local');
        [$quote, $product] = $this->source();
        $path = UploadedFile::fake()->image('pastry.png',240,160)->store('quotation-photos','local');
        $product->media()->create(['kind'=>'image','mime_type'=>'image/png','path'=>$path,'original_name'=>'pastry.png','sort_order'=>0]);
        $before=$quote->getAttributes();
        $image=app(DocumentProductImages::class)->forItems($quote->items)[0];
        foreach (['show','print'] as $route) $this->get(route('admin.quotations.'.$route,$quote))->assertOk()->assertSee('PHOTO')->assertSee($image,false)->assertSee('width="26" height="26"',false);
        $html=view('admin.quotations.print',['quotation'=>$quote,'pdf'=>true])->render();
        $pdf=new Dompdf(new Options(['isRemoteEnabled'=>false,'defaultFont'=>'DejaVu Sans','defaultMediaType'=>'print']));
        $pdf->loadHtml($html);$pdf->setPaper('A4');$pdf->render();
        $this->assertStringStartsWith('%PDF-',$pdf->output());
        $this->assertStringContainsString('/Width 160',$pdf->output());
        Storage::disk('local')->delete($path);
        $this->get(route('admin.quotations.print',$quote))->assertOk()->assertSee('No product photo');
        $this->assertSame($before,$quote->fresh()->getAttributes());
    }
}
