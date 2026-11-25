<?php

namespace Tests\Feature;

use App\Models\{CreditApplication, CreditApplicationTemplate, EmailHistory, GeneralSetting, Role, SmtpSetting, User};
use App\Services\{AdminNavigation, EmailOutbox};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CreditApplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_publication_requires_a_record_of_legal_approval_and_freezes_each_version(): void
    {
        $customer = $this->customer();
        $this->actingAs($customer)->get(route('customer.credit-applications.index'))->assertOk()->assertSee('currently unavailable');
        $this->post(route('customer.credit-applications.start'))->assertSessionHasErrors('application');
        $this->assertDatabaseCount('email_history', 0);

        $staff = $this->reviewer();
        $this->actingAs($staff)->get(route('admin.credit-applications.index'))->assertOk();
        $this->post(route('admin.credit-applications.invite'), ['customer_id' => $customer->customerRecord()->id])->assertSessionHasErrors('application');
        $this->get(route('admin.credit-applications.templates.index'))->assertForbidden();

        $this->actingAs($this->admin())->get(route('admin.credit-applications.templates.create'))->assertOk()->assertSee('Personal guarantee');
        $template = $this->createTemplate();
        $this->get(route('admin.credit-applications.templates.show', $template))->assertOk()->assertSee('Publish after legal approval');
        $this->post(route('admin.credit-applications.templates.publish', $template), ['revision' => 0, 'legal_approval_reference' => 'Synthetic review'])
            ->assertSessionHasErrors('approved');
        $this->assertNull($template->fresh()->published_at);

        $this->put(route('admin.credit-applications.templates.update', $template), $this->wording('Revised test wording') + ['revision' => 0])
            ->assertSessionHasNoErrors();
        $this->post(route('admin.credit-applications.templates.publish', $template), ['revision' => 0, 'approved' => '1', 'legal_approval_reference' => 'Synthetic review'])
            ->assertSessionHasErrors('template');
        $this->publish($template->fresh());
        $this->put(route('admin.credit-applications.templates.update', $template), $this->wording('Unauthorized replacement') + ['revision' => 1])
            ->assertSessionHasErrors('template');
        $this->assertSame('Revised test wording', $template->fresh()->guarantee_terms);
    }

    public function test_customer_signs_the_reviewed_version_and_both_portals_retain_the_same_pdf(): void
    {
        $customer = $this->customer();
        $customer->customerRecord()->update(['alternate_email' => 'alternate@example.test']);
        $this->actingAs($this->admin());
        $version = $this->createTemplate();
        $this->publish($version);
        $this->actingAs($this->reviewer())->post(route('admin.credit-applications.invite'), ['customer_id' => $customer->customerRecord()->id])
            ->assertSessionHasNoErrors();
        $application = CreditApplication::sole();
        $email = $application->invitationEmail;
        $this->assertSame([$customer->email], $email->recipients());
        $invitation = route('customer.credit-applications.open', $application);
        $this->assertStringContainsString($invitation, $email->payload['body']);
        $this->get(route('admin.credit-applications.show', $application))->assertOk()->assertSee('Awaiting signature')->assertSee('Queued');
        SmtpSetting::forceCreate(['id' => 1, 'enabled' => true, 'delivery_method' => 'resend', 'api_key' => 'test-only',
            'from_address' => 'sender@example.test', 'from_name' => 'Test', 'revision' => 1, 'tested_revision' => 1, 'tested_at' => now()]);
        Http::preventStrayRequests();
        Http::fake(['api.resend.com/*' => Http::response(['id' => 'test-credit-invitation'], 200)]);
        $this->assertTrue(app(EmailOutbox::class)->deliver($email->id));
        Http::assertSent(fn ($request) => $request['to'] === [$customer->email] && str_contains($request['text'], $invitation));
        $this->assertSame('accepted', $email->fresh()->status);

        auth()->logout();
        $this->get($invitation)->assertRedirect(route('customer.login'));
        $this->post(route('customer.login.store'), ['email' => $customer->email, 'password' => 'password'])
            ->assertRedirect(route('customer.credit-applications.show', $application));
        $this->post(route('customer.credit-applications.start'))->assertRedirect(route('customer.credit-applications.show', $application));
        $this->assertDatabaseCount('credit_applications', 1);
        $this->assertSame(1, EmailHistory::where('type', 'credit_application_invitation')->count());

        $review = route('customer.credit-applications.show', [$application, 'review' => 1]);
        $prepare = route('customer.credit-applications.prepare', $application);
        $submit = route('customer.credit-applications.submit', $application);
        $this->get(route('customer.credit-applications.show', $application))->assertOk()->assertSee('Applicant details');
        $this->post($prepare, $this->details())->assertRedirect($review);
        $staleToken = $application->fresh()->review_token;
        $this->post($prepare, ['requested_limit' => '1025.50'] + $this->details())->assertRedirect($review);
        $application->refresh();
        $this->get($review)->assertOk()->assertSee($version->guarantee_terms)->assertSee('1,025.50')->assertSee('Electronic signature (full name)');
        $this->get(route('customer.credit-applications.draft', $application))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $data = ['signature' => 'Alex Example', 'review_token' => $application->review_token, 'password' => 'password'];
        $this->from($review)->post($submit, $data)->assertSessionHasErrors('consent');
        $this->from($review)->post($submit, ['review_token' => $staleToken, 'consent' => '1'] + $data)->assertSessionHasErrors('signature');
        $this->from($review)->post($submit, ['password' => 'wrong', 'consent' => '1'] + $data)->assertSessionHasErrors('password');
        $this->from($review)->post($submit, $data + ['consent' => '1'])->assertRedirect(route('customer.credit-applications.show', $application));
        $application->refresh();
        $this->assertSame('submitted', $application->status);
        $this->assertSame($version->version, $application->signed_snapshot['template']['version']);
        $this->assertSame('1025.50', $application->signed_snapshot['details']['requested_limit']);
        $this->assertTrue($application->signed_snapshot['signature']['consent_accepted']);
        $this->assertSame($customer->id, $application->signed_snapshot['signature']['user_id']);
        $this->assertNotNull($application->submitted_at);
        $this->assertNull($application->draft_details);
        $this->assertStringNotContainsString('Alex Example', DB::table('credit_applications')->where('id', $application->id)->value('signed_snapshot'));

        $url = route('customer.documents.file', ['credit-application', $application->id, 'download' => 1]);
        $saved = $this->get($url)->assertOk()->assertDownload(strtolower($application->reference).'-signed.pdf')->getContent();
        $this->assertStringStartsWith('%PDF-', $saved);
        $this->assertSame($application->pdf_sha256, hash('sha256', $saved));
        $this->get(route('customer.documents', ['document_type' => 'credit']))->assertOk()->assertSee('Signed credit application '.$application->reference);
        $this->post($submit, $data + ['consent' => '1'])->assertSessionHasErrors('application');
        $this->post(route('customer.credit-applications.start'))->assertSessionHasErrors('application');

        $this->actingAs($this->customer())->get($url)->assertNotFound();
        $this->post($submit, $data + ['consent' => '1'])->assertNotFound();
        $reader = User::factory()->create(['is_active' => true, 'role_id' => Role::create(['name' => 'Orders reader', 'permissions' => ['orders.view']])->id]);
        $this->actingAs($reader)->get(route('admin.credit-applications.document', $application))->assertForbidden();
        $this->get(route('admin.customers.documents.file', [$application->customer_id, 'credit-application', $application->id]))->assertForbidden();

        $this->actingAs($this->admin());
        $new = $this->createTemplate('New synthetic guarantee wording');
        $this->publish($new);
        GeneralSetting::findOrFail(1)->update(['document_company_name' => 'Changed Brand After Signature']);
        $staff = $this->reviewer();
        $this->actingAs($staff)->get(route('admin.credit-applications.show', $application))->assertOk()->assertSee('Alex Example')->assertSee('Pending review');
        $staffPdf = $this->get(route('admin.credit-applications.document', $application))->assertOk()->getContent();
        $this->assertSame($saved, $staffPdf);
        $this->patch(route('admin.credit-applications.review', $application), ['status' => 'approved', 'review_note' => 'Application accepted.'])->assertSessionHasNoErrors();
        $this->assertSame($staff->id, $application->fresh()->reviewed_by);
        $this->assertSame(0, (int) $customer->customerRecord()->credit_limit_cents);
        $this->actingAs($customer)->get(route('customer.credit'))->assertOk()->assertSee('Approved');
        $this->get(route('customer.credit-applications.show', $application))->assertOk()->assertSee('Application accepted.');
        $this->assertSame($saved, $this->get($url)->assertOk()->getContent());
    }

    public function test_retired_form_cannot_be_signed_and_staff_can_reject_with_a_note(): void
    {
        $staff = $this->admin();
        $customer = $this->customer();
        $this->actingAs($staff);
        $old = $this->createTemplate();
        $this->publish($old);
        $this->actingAs($customer)->post(route('customer.credit-applications.start'))->assertSessionHasNoErrors();
        $application = CreditApplication::sole();
        $this->post(route('customer.credit-applications.prepare', $application), $this->details())->assertSessionHasNoErrors();
        $data = ['signature' => 'Alex Example', 'password' => 'password', 'consent' => '1', 'review_token' => $application->fresh()->review_token];
        $this->actingAs($staff)->post(route('admin.credit-applications.templates.retire', $old))->assertSessionHasNoErrors();
        $this->actingAs($customer)->post(route('customer.credit-applications.submit', $application), $data)->assertSessionHasErrors('application');
        $this->actingAs($staff);
        $this->publish($this->createTemplate('Replacement guarantee'));
        $this->actingAs($customer)->post(route('customer.credit-applications.start'))->assertSessionHasNoErrors();
        $this->assertSame('superseded', $application->fresh()->status);
        $new = CreditApplication::latest('id')->firstOrFail();
        $this->post(route('customer.credit-applications.prepare', $new), $this->details())->assertSessionHasNoErrors();
        $this->post(route('customer.credit-applications.submit', $new), ['review_token' => $new->fresh()->review_token] + $data)->assertSessionHasNoErrors();
        $this->actingAs($staff)->patch(route('admin.credit-applications.review', $new), ['status' => 'rejected'])->assertSessionHasErrors('review_note');
        $this->patch(route('admin.credit-applications.review', $new), ['status' => 'rejected', 'review_note' => 'Please contact our credit team.'])->assertSessionHasNoErrors();
        $this->actingAs($customer)->get(route('customer.credit-applications.show', $new))->assertOk()->assertSee('Rejected')->assertSee('Please contact our credit team.');
    }

    private function details(): array
    {
        return [
            'applicant_name' => 'Example Company', 'contact_name' => 'Alex Example', 'contact_phone' => '4165550100',
            'billing_address' => "10 Test Street\nToronto, ON", 'requested_limit' => '1000',
            'guarantor_name' => 'Alex Example', 'guarantor_address' => "20 Test Road\nMarkham, ON", 'signing_title' => 'Owner',
        ];
    }

    private function wording(string $guarantee): array
    {
        return ['title' => 'Credit Application & Personal Guarantee', 'application_terms' => 'Application fixture for testing.',
            'guarantee_terms' => $guarantee, 'consent_statement' => 'I accept this synthetic form for testing.'];
    }

    private function createTemplate(string $guarantee = 'Synthetic guarantee fixture for tests only.'): CreditApplicationTemplate
    {
        $this->post(route('admin.credit-applications.templates.store'), $this->wording($guarantee))->assertSessionHasNoErrors();
        return CreditApplicationTemplate::latest('id')->firstOrFail();
    }

    private function publish(CreditApplicationTemplate $template): void
    {
        $this->post(route('admin.credit-applications.templates.publish', $template), [
            'revision' => $template->revision, 'approved' => '1', 'legal_approval_reference' => 'Synthetic test fixture',
        ])->assertSessionHasNoErrors();
    }

    private function customer(): User
    {
        return User::factory()->create(['is_active' => true, 'account_type' => 'individual', 'email' => fake()->unique()->safeEmail()]);
    }

    private function admin(): User
    {
        return User::factory()->create(['is_active' => true, 'role_id' => Role::where('is_super', true)->value('id')]);
    }

    private function reviewer(): User
    {
        $role = Role::firstOrCreate(['name' => 'Credit reviewer'], ['permissions' => AdminNavigation::normalize(['access.admin.credit-applications.index'])]);
        return User::factory()->create(['is_active' => true, 'role_id' => $role->id]);
    }
}
