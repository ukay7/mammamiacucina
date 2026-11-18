<?php

namespace Tests\Feature;

use App\Models\{EmailHistory, SmtpSetting, User};
use App\Services\{CustomerEmails, EmailOutbox};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Http, Mail};
use Tests\TestCase;

class BusinessRegistrationNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function input(array $extra=[]): array
    {
        return $extra + ['name'=>'Alex Owner','email'=>'owner@example.test','phone'=>'4165551234','account_type'=>'business',
            'business_name'=>'Bakery <script>example</script>', 'business_bin'=>'BIN-123', 'business_phone'=>'4165559999',
            'business_email'=>'business@example.test','password'=>'strong-password-123','password_confirmation'=>'strong-password-123'];
    }

    public function test_registration_queues_one_private_office_alert_with_full_details_and_no_approval(): void
    {
        Mail::fake(); Http::preventStrayRequests();
        $this->post(route('customer.register.store'),$this->input())->assertSessionHasNoErrors();
        $user=User::where('email','owner@example.test')->sole();
        $entry=EmailHistory::where('type','business_registration_notification')->sole();
        $this->assertNull($user->email_verified_at); $this->assertNull($user->business_approved_at);
        $this->assertSame(['info@mammamiacucina.ca'],$entry->recipients());
        $this->assertSame('queued',$entry->status);
        foreach(['Alex Owner','BIN-123','4165559999','business@example.test','Awaiting customer email verification',route('admin.customers.show',$user->customerRecord())] as $value) $this->assertStringContainsString($value,$entry->payload['body']);
        $this->assertStringContainsString('&lt;script&gt;', $entry->payload['html']);
        $this->assertStringNotContainsString('<script>', $entry->payload['html']);
        $this->assertStringNotContainsString('strong-password', json_encode($entry->payload));
        $this->assertStringNotContainsString('BIN-123', DB::table('email_history')->where('id',$entry->id)->value('payload'));
        app(CustomerEmails::class)->businessRegistration($user);
        $this->post(route('customer.verify.resend'))->assertRedirect();
        $this->assertSame(1,EmailHistory::where('type','business_registration_notification')->count());
        $this->assertAuthenticatedAs($user);
        Http::assertNothingSent();
    }

    public function test_invalid_and_individual_registrations_do_not_alert_office(): void
    {
        Mail::fake();
        $this->post(route('customer.register.store'),$this->input(['business_bin'=>'']))->assertSessionHasErrors('business_bin');
        $this->post(route('customer.register.store'),$this->input(['account_type'=>'individual']))->assertSessionHasNoErrors();
        $this->assertSame(0,EmailHistory::where('type','business_registration_notification')->count());
    }

    public function test_smtp_path_sends_both_html_and_plain_text_without_external_delivery(): void
    {
        config(['mail.default'=>'array']);
        $this->post(route('customer.register.store'),$this->input())->assertSessionHasNoErrors();
        SmtpSetting::forceCreate(['id'=>1,'enabled'=>true,'delivery_method'=>'smtp','from_address'=>'mail@example.test','from_name'=>'Mamma Mia','revision'=>1,'tested_revision'=>1,'tested_at'=>now()]);
        $this->mock(\App\Services\SmtpConfiguration::class,fn($mock)=>$mock->shouldReceive('apply')->once()->andReturnUsing(function(){
            config(['mail.mailers.configured_smtp'=>['transport'=>'array']]);
        }));
        $entry=EmailHistory::where('type','business_registration_notification')->sole();
        $this->assertTrue(app(EmailOutbox::class)->deliver($entry->id));
        $mail=Mail::mailer('configured_smtp')->getSymfonyTransport()->messages()->sole()->getOriginalMessage();
        $this->assertSame('info@mammamiacucina.ca',$mail->getTo()[0]->getAddress());
        $this->assertStringContainsString('Review business application',$mail->getHtmlBody());
        $this->assertStringContainsString('BIN-123',$mail->getTextBody());
    }

    public function test_office_alert_uses_configured_address_and_retries_the_same_email(): void
    {
        Mail::fake();config(['mail.business_notification_address'=>'office@example.test']);
        $this->post(route('customer.register.store'),$this->input())->assertSessionHasNoErrors();
        $entry=EmailHistory::where('type','business_registration_notification')->sole();
        $other=User::factory()->create(['account_type'=>'individual','email'=>'office@example.test','role_id'=>\App\Models\Role::where('name','Customer')->value('id')]);
        $other->customerRecord()->update(['alternate_email'=>'private@example.test']);
        $this->assertNull(app(\App\Services\CustomerEmailRecipients::class)->alternate($entry->type,$entry->recipient,$other->customerRecord()));
        SmtpSetting::forceCreate(['id'=>1,'enabled'=>true,'delivery_method'=>'resend','api_key'=>'fake-key','from_address'=>'mail@example.test','from_name'=>'Mamma Mia','revision'=>1,'tested_revision'=>1,'tested_at'=>now()]);
        Http::fake(['api.resend.com/*'=>Http::sequence()->push([],503)->push(['id'=>'registration-email'],200)]);
        $worker=app(EmailOutbox::class);
        $this->assertFalse($worker->deliver($entry->id));
        $this->travel(3)->minutes();$this->assertTrue($worker->deliver($entry->id));
        $this->assertFalse($worker->deliver($entry->id));
        $requests=Http::recorded();$this->assertCount(2,$requests);
        $this->assertSame($requests[0][0]->header('Idempotency-Key'),$requests[1][0]->header('Idempotency-Key'));
        Http::assertSent(fn($r)=>$r['to']===['office@example.test'] && str_contains($r['html'],'Review business application') && str_contains($r['text'],'BIN-123'));
        $this->assertSame('accepted',$entry->fresh()->status);
    }
}
