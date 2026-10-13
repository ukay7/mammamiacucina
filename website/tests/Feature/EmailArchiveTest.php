<?php

namespace Tests\Feature;

use App\Models\{EmailHistory, Role, User};
use App\Services\{EmailArchive, OutgoingEmail};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EmailArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_saved_body_is_encrypted_redacted_escaped_and_permission_protected(): void
    {
        config(['mail.default' => 'array']);
        $entry = app(OutgoingEmail::class)->raw("Hello Buyer\n<script>alert(1)</script>\nhttps://example.test/account/reset-password/secret?email=a@example.test", fn ($m) => $m->to('buyer@example.test')->subject('Reset password'), 'password_reset');
        $this->assertStringContainsString('Hello Buyer', $entry->archive['body']);
        $this->assertStringNotContainsString('secret', $entry->archive['body']);
        $this->assertStringNotContainsString('Hello Buyer', DB::table('email_history')->where('id', $entry->id)->value('archive'));
        $this->assertArrayNotHasKey('archive', $entry->toArray());
        $url = route('admin.email.history.show', $entry);
        $this->get($url)->assertRedirect();
        $staff = User::factory()->create(['is_active'=>true, 'role_id'=>Role::create(['name'=>'No email access', 'permissions'=>[]])->id]);
        $this->actingAs($staff)->get($url)->assertForbidden();
        $this->get(route('admin.email.history.attachment', $entry))->assertForbidden();
        $admin = User::factory()->create(['is_active'=>true, 'role_id'=>Role::where('is_super',true)->value('id')]);
        $this->actingAs($admin)->get($url)->assertOk()->assertSee('Hello Buyer')->assertSee('[Private link hidden]')->assertDontSee('secret')->assertSee('&lt;script&gt;',false)->assertDontSee('<script>alert',false)->assertHeader('Cache-Control','no-store, private');
        $this->get(route('admin.email.history'))->assertOk()->assertSee('View Email')->assertDontSee('Hello Buyer');
        $this->get(route('admin.email.history.attachment', $entry))->assertNotFound();
    }

    public function test_receipt_download_and_legacy_history(): void
    {
        $admin = User::factory()->create(['is_active'=>true,'role_id'=>Role::where('is_super',true)->value('id')]);
        $this->actingAs($admin);
        $pdf = "%PDF-1.4\narchived receipt";
        $entry = EmailHistory::create(['recipient'=>'buyer@example.test','subject'=>'Order received','type'=>'order_confirmation','status'=>'accepted','archive'=>EmailArchive::snapshot('order_confirmation','Order total $42.00',base64_encode($pdf)),'archived_at'=>now()]);
        $this->get(route('admin.email.history.show',$entry))->assertOk()->assertSee('Order total $42.00')->assertSee('Download attached receipt');
        $this->get(route('admin.email.history.attachment',$entry))->assertOk()->assertHeader('Content-Type','application/pdf')->assertHeader('Cache-Control','no-store, private')->assertContent($pdf);
        $entry->update(['archive'=>null,'archived_at'=>null]);
        $this->get(route('admin.email.history.show',$entry))->assertOk()->assertSee('Content is unavailable');
        $this->get(route('admin.email.history.attachment',$entry))->assertNotFound();
    }

    public function test_private_links_are_hidden_without_changing_ordinary_order_links(): void
    {
        $result = EmailArchive::snapshot('order_status', 'Track https://example.test/track-order/abc Reset /account/reset-password/secret Signed https://example.test/anything?signature=private');
        $this->assertStringContainsString('https://example.test/track-order/abc', $result['body']);
        $this->assertStringNotContainsString('secret', $result['body']);
        $this->assertStringNotContainsString('signature=', $result['body']);
        $this->assertTrue($result['redacted']);
    }
}
