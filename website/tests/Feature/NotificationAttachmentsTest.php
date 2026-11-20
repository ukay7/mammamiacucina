<?php
namespace Tests\Feature;

use App\Models\{NotificationCampaign, Role, SmtpSetting, User};
use App\Services\Notifications\{Attachments, Campaigns, Outbox};
use App\Services\StaffPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Http, Mail, Storage};
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationAttachmentsTest extends TestCase
{
    use RefreshDatabase;
    private array $fixtures=[];

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url'=>'https://mmc.example.test']);
        Storage::fake('local');
        $this->actingAs(User::factory()->create(['is_active'=>true,'role_id'=>Role::where('is_super',true)->value('id')]));
        SmtpSetting::forceCreate(['id'=>1,'enabled'=>true,'delivery_method'=>'resend','api_key'=>'re_fake','from_address'=>'sender@example.test','from_name'=>'Mamma Mia','revision'=>0,'tested_revision'=>0,'tested_at'=>now()]);
        $this->fakeProvider();
    }
    private function fakeProvider($response=null): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::preventStrayRequests();
        Http::fake(['api.resend.com/emails'=>$response ?? fn()=>Http::response(['id'=>(string)Str::uuid()],200)]);
    }
    private function data(array $replace=[]): array
    {
        return array_replace(['title'=>'Catalogue update','subject'=>'Your documents','body'=>'Please see the attached files.',
            'audience_mode'=>'selected','customer_type'=>'all','audience'=>['email:one@example.test','email:two@example.test'],
            'include_alternate'=>true,'track_opens'=>true,'track_clicks'=>true,'revision'=>0],$replace);
    }
    private function pdf(string $name='Catalogue.pdf', ?int $bytes=null): UploadedFile
    {
        $content="%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n%%EOF\n";
        return $this->realFile($name,$bytes ? str_pad($content,$bytes,' ') : $content);
    }
    private function realFile(string $name, string $content): UploadedFile
    {
        // Laravel's fake File trusts the filename for MIME; use the real detector for validation coverage.
        $fake=UploadedFile::fake()->createWithContent($name,$content); $this->fixtures[]=$fake;
        return new UploadedFile($fake->getRealPath(),$name,null,UPLOAD_ERR_OK,true);
    }
    private function draft(?array $files=null): NotificationCampaign
    {
        $this->post(route('admin.notifications.store'),$this->data(['attachments'=>$files ?? [$this->pdf()]]))->assertSessionHasNoErrors()->assertRedirect();
        return NotificationCampaign::latest('id')->firstOrFail();
    }
    private function tested(NotificationCampaign $campaign): NotificationCampaign
    {
        $test=app(Campaigns::class)->test($campaign,$campaign->revision,'tester@example.test',(string)Str::uuid());
        $this->assertSame('accepted',$test->status);
        return $campaign->fresh();
    }
    private function queued(NotificationCampaign $campaign): NotificationCampaign
    {
        $campaign=$this->tested($campaign);
        $review=app(Campaigns::class)->review($campaign,$campaign->revision,auth()->id(),null);
        return app(Campaigns::class)->queue($campaign,$review['token'],auth()->id());
    }
    private function updateDraft(NotificationCampaign $c, array $replace=[])
    {
        return $this->put(route('admin.notifications.update',$c),$this->data(array_replace(['revision'=>$c->revision],$replace)));
    }

    public function test_uploads_are_private_encrypted_and_downloadable_with_original_names(): void
    {
        $pdf=$this->pdf('Price list.pdf'); $bytes=file_get_contents($pdf->getRealPath());
        $c=$this->draft([$pdf,UploadedFile::fake()->image('Photo.png',20,20)]);
        $this->assertCount(2,$c->attachments);
        $file=$c->attachments[0];
        Storage::disk('local')->assertExists($file['path']);
        $this->assertSame($bytes,Storage::disk('local')->get($file['path']));
        $this->assertStringNotContainsString('Price list.pdf',DB::table('notification_campaigns')->where('id',$c->id)->value('attachments'));
        $this->assertArrayNotHasKey('attachments',$c->toArray());
        $this->get(route('admin.notifications.edit',$c))->assertOk()->assertSee('multipart/form-data')->assertSee('Price list.pdf')->assertDontSee($file['path']);
        $this->get(route('admin.notifications.show',$c))->assertOk()->assertSee('Price list.pdf');
        $this->get(route('admin.notifications.attachment',[$c,$file['id']]))->assertOk()->assertDownload('Price list.pdf')->assertHeader('X-Content-Type-Options','nosniff')->assertStreamedContent($bytes);
        Http::assertNothingSent();
    }
    public function test_both_test_and_every_bulk_email_receive_identical_attachment_bytes(): void
    {
        $c=$this->draft([$this->pdf(),UploadedFile::fake()->image('Photo.jpg')]);
        $expected=array_map(fn($file)=>['filename'=>$file['name'],'content'=>base64_encode(Storage::disk('local')->get($file['path']))],$c->attachments);
        $c=$this->queued($c);
        $this->get(route('admin.notifications.show',$c))->assertOk()->assertSee('Catalogue.pdf');
        foreach ($c->deliveries()->where('is_test',false)->get() as $delivery) {
            $this->assertTrue(app(Outbox::class)->deliver($delivery->id));
            $this->get(route('admin.notifications.delivery',[$c,$delivery]))->assertOk()->assertSee('Photo.jpg');
        }
        Http::assertSentCount(3);
        foreach (Http::recorded() as [$request]) { $this->assertSame($expected,$request['attachments']); $this->assertCount(1,$request['to']); }
        $this->assertSame(['tester@example.test'],Http::recorded()[0][0]['to']);
        $this->assertSame('completed',$c->fresh()->status);
    }
    public function test_saved_files_survive_edits_and_removed_files_remain_on_historical_tests(): void
    {
        $c=$this->tested($this->draft()); $file=$c->attachments[0]; $test=$c->deliveries()->where('is_test',true)->firstOrFail();
        $this->updateDraft($c,['subject'=>'Changed subject'])->assertSessionHasNoErrors(); $c->refresh();
        $this->assertSame([$file],$c->attachments); $this->assertNull($c->tested_revision);
        $c=$this->tested($c);
        $this->updateDraft($c,['remove_attachments'=>[$file['id']]])->assertSessionHasNoErrors(); $c->refresh();
        $this->assertSame([],$c->attachments); $this->assertNull($c->tested_revision);
        $this->post(route('admin.notifications.review',$c),['revision'=>$c->revision])->assertSessionHasErrors('notification');
        Storage::disk('local')->assertExists($file['path']);
        $this->get(route('admin.notifications.attachment',[$c,$file['id']]))->assertNotFound();
        $this->get(route('admin.notifications.delivery-attachment',[$c,$test,$file['id']]))->assertOk()->assertDownload('Catalogue.pdf');
        $this->get(route('admin.notifications.delivery',[$c,$test]))->assertOk()->assertSee('Catalogue.pdf');
    }
    public function test_duplicate_keeps_files_but_changes_do_not_affect_the_queued_original(): void
    {
        $c=$this->queued($this->draft()); $file=$c->attachments[0];
        $this->post(route('admin.notifications.duplicate',$c))->assertSessionHasNoErrors();
        $copy=NotificationCampaign::latest('id')->firstOrFail();
        $this->assertNotSame($c->id,$copy->id); $this->assertSame($c->attachments,$copy->attachments); $this->assertNull($copy->tested_revision);
        $this->updateDraft($copy,['remove_attachments'=>[$file['id']]])->assertSessionHasNoErrors();
        $this->delete(route('admin.notifications.destroy',$copy))->assertRedirect();
        $this->assertSame([$file],$c->fresh()->attachments);
        $this->fakeProvider();
        $this->assertTrue(app(Outbox::class)->deliver($c->deliveries()->where('is_test',false)->first()->id));
        Http::assertSent(fn($request)=>$request['attachments'][0]['filename']==='Catalogue.pdf');
        Storage::disk('local')->assertExists($file['path']);
    }
    public function test_size_count_extension_and_content_limits_are_enforced(): void
    {
        $cases=[
            [array_map(fn($i)=>$this->pdf("$i.pdf"),range(1,6)),'attachments'],
            [[$this->pdf('Large.pdf',Attachments::MAX_BYTES+1)],'attachments.0'],
            [[$this->pdf('First.pdf',6*1024*1024),$this->pdf('Second.pdf',5*1024*1024)],'attachments'],
            [[$this->realFile('Unsafe.pdf','<html><script>alert(1)</script></html>')],'attachments.0'],
            [[$this->pdf('Unsafe.exe')],'attachments.0'],
            [[$this->realFile('Empty.pdf','')],'attachments.0'],
        ];
        foreach ($cases as [$files,$field]) $this->post(route('admin.notifications.store'),$this->data(['attachments'=>$files]))->assertSessionHasErrors($field);
        $this->assertSame(0,NotificationCampaign::count()); $this->assertSame([],Storage::disk('local')->allFiles());
        $c=$this->draft([$this->pdf('At limit.pdf',Attachments::MAX_BYTES)]);
        $this->assertSame(Attachments::MAX_BYTES,$c->attachments[0]['size']);
        $this->updateDraft($c,['attachments'=>[$this->pdf()]])->assertSessionHasErrors('attachments');
        $this->updateDraft($c,['remove_attachments'=>[$c->attachments[0]['id']],'attachments'=>[$this->pdf('Replacement.pdf')]])->assertSessionHasNoErrors();
        $this->assertSame('Replacement.pdf',$c->fresh()->attachments[0]['name']);
        Http::assertNothingSent();
    }
    public function test_five_saved_files_count_toward_the_limit_and_can_be_replaced(): void
    {
        $c=$this->draft(array_map(fn($i)=>$this->pdf("$i.pdf"),range(1,5)));
        $this->updateDraft($c,['attachments'=>[$this->pdf()]])->assertSessionHasErrors('attachments');
        $this->assertCount(5,Storage::disk('local')->allFiles());
        $this->updateDraft($c,['remove_attachments'=>[$c->attachments[0]['id']],'attachments'=>[$this->pdf()]])->assertSessionHasNoErrors();
        $this->assertCount(5,$c->fresh()->attachments);
    }
    public function test_foreign_file_ids_stale_edits_and_queued_edits_cannot_change_attachments(): void
    {
        $c=$this->draft(); $other=$this->draft(); $before=$c->attachments;
        $this->updateDraft($c,['remove_attachments'=>[$other->attachments[0]['id']]])->assertSessionHasErrors('attachments');
        $this->updateDraft($c,['revision'=>99,'attachments'=>[$this->pdf('Stale.pdf')]])->assertSessionHasErrors('notification');
        $this->assertSame($before,$c->fresh()->attachments); $this->assertCount(2,Storage::disk('local')->allFiles());
        $c=$this->queued($c);
        $this->updateDraft($c,['remove_attachments'=>[$before[0]['id']]])->assertSessionHasErrors('notification');
        $this->assertSame($before,$c->fresh()->attachments);
    }
    public function test_attachment_downloads_require_permission_and_match_the_campaign_and_version(): void
    {
        $c=$this->tested($this->draft()); $other=$this->draft(); $file=$c->attachments[0]; $test=$c->deliveries()->firstOrFail();
        $this->get(route('admin.notifications.attachment',[$other,$file['id']]))->assertNotFound();
        $this->get(route('admin.notifications.delivery-attachment',[$other,$test,$file['id']]))->assertNotFound();
        $role=Role::create(['name'=>'Notification viewer','permissions'=>StaffPermissions::normalize(['access.admin.notifications.index'])]);
        $viewer=User::factory()->create(['is_active'=>true,'role_id'=>$role->id]); $this->actingAs($viewer);
        $this->get(route('admin.notifications.attachment',[$c,$file['id']]))->assertOk();
        $this->get(route('admin.notifications.delivery-attachment',[$c,$test,$file['id']]))->assertOk();
        $this->updateDraft($c,['remove_attachments'=>[$file['id']]])->assertForbidden();
        $role->update(['permissions'=>StaffPermissions::normalize(['access.admin.dashboard'])]); $this->actingAs($viewer->fresh());
        $this->get(route('admin.notifications.attachment',[$c,$file['id']]))->assertForbidden();
        $this->actingAs(User::factory()->create(['account_type'=>'individual','is_active'=>true]));
        $this->get(route('admin.notifications.attachment',[$c,$file['id']]))->assertRedirect(route('customer.orders'));
    }
    public function test_missing_or_changed_files_block_test_review_queue_and_bulk_send(): void
    {
        $c=$this->draft(); $file=$c->attachments[0]; $bytes=Storage::disk('local')->get($file['path']);
        Storage::disk('local')->delete($file['path']);
        $this->post(route('admin.notifications.test',$c),['revision'=>0,'recipient'=>'tester@example.test','test_key'=>(string)Str::uuid()])->assertSessionHasErrors('attachments');
        Http::assertNothingSent();
        Storage::disk('local')->put($file['path'],$bytes); $c=$this->tested($c);
        Storage::disk('local')->put($file['path'],str_repeat('x',strlen($bytes)));
        $this->post(route('admin.notifications.review',$c),['revision'=>0])->assertSessionHasErrors('attachments');
        Storage::disk('local')->put($file['path'],$bytes);
        $review=app(Campaigns::class)->review($c,0,auth()->id(),null);
        Storage::disk('local')->delete($file['path']);
        $this->post(route('admin.notifications.queue',$c),['review_token'=>$review['token'],'confirmed'=>1])->assertSessionHasErrors('attachments');
        Storage::disk('local')->put($file['path'],$bytes); $c=app(Campaigns::class)->queue($c,$review['token'],auth()->id());
        Storage::disk('local')->put($file['path'],str_repeat('x',strlen($bytes))); $this->fakeProvider();
        $delivery=$c->deliveries()->where('is_test',false)->firstOrFail();
        $this->assertFalse(app(Outbox::class)->deliver($delivery->id));
        $this->assertSame('needs_review',$delivery->fresh()->status); $this->assertStringContainsString('No email was sent',$delivery->fresh()->note);
        $this->assertFalse(app(Outbox::class)->deliver($delivery->id)); Http::assertNothingSent();
        $this->get(route('admin.notifications.attachment',[$c,$file['id']]))->assertNotFound();
    }
    public function test_retry_preserves_attachment_bytes_and_idempotency_key(): void
    {
        $c=$this->queued($this->draft()); $delivery=$c->deliveries()->where('is_test',false)->firstOrFail();
        $this->fakeProvider(Http::sequence()->push([],500)->push(['id'=>'accepted'],200));
        $this->assertFalse(app(Outbox::class)->deliver($delivery->id)); $delivery->refresh();
        $this->assertSame('retry',$delivery->status);
        $this->assertArrayHasKey('attachments',$delivery->payload);
        $this->assertArrayNotHasKey('content',$delivery->payload['attachments'][0]);
        $this->travelTo($delivery->available_at->copy()->addSecond());
        $this->assertTrue(app(Outbox::class)->deliver($delivery->id));
        $requests=Http::recorded(); $this->assertSame($requests[0][0]->data(),$requests[1][0]->data());
        $this->assertSame($requests[0][0]->header('Idempotency-Key'),$requests[1][0]->header('Idempotency-Key'));
    }
    public function test_smtp_messages_include_downloadable_attachments_and_text_and_html(): void
    {
        SmtpSetting::find(1)->update(['delivery_method'=>'smtp','host'=>'smtp.example.test','port'=>587,'username'=>'test','password'=>'secret','encryption'=>'tls']);
        $messages=[]; $mailer=\Mockery::mock(\Illuminate\Mail\Mailer::class);
        Mail::shouldReceive('purge')->with('configured_smtp'); Mail::shouldReceive('mailer')->with('configured_smtp')->andReturn($mailer);
        $mailer->shouldReceive('html')->twice()->andReturnUsing(function($html,$callback) use(&$messages) {
            $email=new \Symfony\Component\Mime\Email; $email->html($html); $callback(new \Illuminate\Mail\Message($email)); $messages[]=$email;
            return new \Illuminate\Mail\SentMessage(new \Symfony\Component\Mailer\SentMessage($email,\Symfony\Component\Mailer\Envelope::create($email)));
        });
        $c=$this->queued($this->draft());
        $this->assertTrue(app(Outbox::class)->deliver($c->deliveries()->where('is_test',false)->first()->id));
        foreach ($messages as $email) {
            $this->assertNotEmpty($email->getTextBody()); $this->assertNotEmpty($email->getHtmlBody());
            $this->assertCount(1,$email->getAttachments()); $attachment=$email->getAttachments()[0];
            $this->assertSame('Catalogue.pdf',$attachment->getFilename());
            $this->assertSame('application/pdf',$attachment->getMediaType().'/'.$attachment->getMediaSubtype());
            $this->assertSame(Storage::disk('local')->get($c->attachments[0]['path']),$attachment->getBody());
        }
        Http::assertNothingSent();
    }
    public function test_legacy_drafts_and_snapshots_without_attachments_still_send(): void
    {
        $c=$this->draft([]); $c->update(['attachments'=>null]); $c=$this->queued($c);
        $snapshot=$c->snapshot; unset($snapshot['attachments']); $c->update(['snapshot'=>$snapshot]);
        $this->assertTrue(app(Outbox::class)->deliver($c->deliveries()->where('is_test',false)->first()->id));
        foreach (Http::recorded() as [$request]) $this->assertArrayNotHasKey('attachments',$request->data());
    }
    public function test_real_office_documents_and_images_are_accepted(): void
    {
        $files=[];
        foreach (['docx'=>'word/document.xml','xlsx'=>'xl/workbook.xml'] as $extension=>$main) {
            $type=$extension==='docx' ? 'wordprocessingml.document' : 'spreadsheetml.sheet';
            $path=tempnam(sys_get_temp_dir(),'notification-office-');
            $zip=new \ZipArchive; $zip->open($path,\ZipArchive::CREATE|\ZipArchive::OVERWRITE);
            $zip->addFromString('[Content_Types].xml','<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/'.$main.'" ContentType="application/vnd.openxmlformats-officedocument.'.$type.'.main+xml"/></Types>');
            $zip->addFromString('_rels/.rels','<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="'.$main.'"/></Relationships>');
            $zip->addFromString($main,$extension==='docx' ? '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Test</w:t></w:r></w:p></w:body></w:document>' : '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheets/></workbook>');
            $zip->close(); $files[]=$this->realFile('Document.'.$extension,file_get_contents($path)); unlink($path);
        }
        foreach (['png','jpg','webp'] as $extension) {
            $image=UploadedFile::fake()->image('Photo.'.$extension,20,20);
            $files[]=$this->realFile($image->getClientOriginalName(),file_get_contents($image->getRealPath()));
        }
        $c=$this->draft($files); $this->assertCount(5,$c->attachments);
    }
}
