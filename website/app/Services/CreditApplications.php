<?php

namespace App\Services;

use App\Models\{CreditApplication, CreditApplicationTemplate, Customer, EmailHistory, GeneralSetting, User};
use App\Support\BusinessTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreditApplications
{
    public function activeTemplate(): ?CreditApplicationTemplate
    {
        return CreditApplicationTemplate::whereNotNull('published_at')->whereNotNull('legal_approval_reference')
            ->whereNull('retired_at')->latest('id')->first();
    }

    public function publish(CreditApplicationTemplate $template, int $revision, string $reference, User $staff): void
    {
        DB::transaction(function () use ($template, $revision, $reference, $staff) {
            GeneralSetting::lockForUpdate()->findOrFail(1);
            $current = CreditApplicationTemplate::lockForUpdate()->findOrFail($template->id);
            if ($current->published_at || $current->revision !== $revision) {
                $this->fail('template', 'The draft changed or was already published. Open the current version and review it again.');
            }
            CreditApplicationTemplate::whereNotNull('published_at')->whereNull('retired_at')->update(['retired_at' => now()]);
            $current->update(['legal_approval_reference' => $reference, 'published_by' => $staff->id, 'published_at' => now()]);
        }, 3);
    }

    public function retire(CreditApplicationTemplate $template): void
    {
        DB::transaction(function () use ($template) {
            GeneralSetting::lockForUpdate()->findOrFail(1);
            $current = CreditApplicationTemplate::lockForUpdate()->findOrFail($template->id);
            if ($current->published_at && ! $current->retired_at) {
                $current->update(['retired_at' => now()]);
            }
        }, 3);
    }

    public function start(User $actor, Customer $customer): CreditApplication
    {
        return DB::transaction(function () use ($actor, $customer) {
            GeneralSetting::lockForUpdate()->findOrFail(1);
            $owner = User::lockForUpdate()->findOrFail($customer->user_id);
            $customer = Customer::lockForUpdate()->findOrFail($customer->id);
            abort_unless($actor->isCustomer() ? $actor->id === $owner->id : $actor->hasAdminPermission('credit-applications.manage'), 403);
            if (! $owner->isCustomer() || ! $owner->is_active || ! $owner->email_verified_at || $owner->businessApprovalPending()
                || ! filter_var($owner->email, FILTER_VALIDATE_EMAIL)) {
                $this->fail('application', 'The customer must have an active account with a verified email address before starting a credit application.');
            }
            if ($customer->creditApplications()->where('status', 'submitted')->exists()) {
                $this->fail('application', 'A submitted application is awaiting review.');
            }
            $template = $this->activeTemplate();
            if (! $template) {
                $this->fail('application', 'Credit applications are unavailable until an approved template is published.');
            }
            $open = $customer->creditApplications()->where('status', 'awaiting_signature')->latest('id')->first();
            if ($open && $open->template_id === $template->id) {
                return $open;
            }
            if ($open) {
                $open->update(['status' => 'superseded', 'draft_details' => null, 'review_token' => null]);
            }
            $application = $customer->creditApplications()->create(['template_id' => $template->id,
                'requested_by' => $actor->id, 'status' => 'awaiting_signature']);
            $key = 'credit-application-invitation:'.$application->id;
            app(CustomerEmails::class)->queue($key, 'credit_application_invitation', $owner->email, [
                'name' => $owner->name, 'reference' => $application->reference,
                'application_url' => route('customer.credit-applications.open', $application),
            ], customer: $customer);
            $application->update(['invitation_email_id' => EmailHistory::where('event_key', $key)->value('id')]);

            return $application;
        }, 3);
    }

    public function prepare(CreditApplication $application, User $owner, array $details): void
    {
        DB::transaction(function () use ($application, $owner, $details) {
            $current = $this->lockDraft($application, $owner);
            $current->update([
                'draft_details' => ['details' => $details, 'brand' => app(DocumentTheme::class)->brand()],
                'review_token' => Str::random(64), 'prepared_at' => now(),
            ]);
        }, 3);
    }

    public function submit(CreditApplication $application, User $owner, array $input, CreditApplicationDocuments $documents): void
    {
        DB::transaction(function () use ($application, $owner, $input, $documents) {
            $current = $this->lockDraft($application, $owner);
            if (! $current->review_token || ! hash_equals($current->review_token, $input['review_token'])) {
                $this->fail('signature', 'The application changed. Review the latest details before signing.');
            }
            if (! Hash::check($input['password'], $current->customer->user->password)) {
                $this->fail('password', 'Enter your current account password to sign.');
            }
            $canonical = fn (string $name) => mb_strtolower(preg_replace('/\s+/u', ' ', trim($name)));
            if ($canonical($input['signature']) !== $canonical($current->draft_details['details']['guarantor_name'])) {
                $this->fail('signature', 'Type the guarantor’s full name exactly as entered on the application.');
            }

            $submittedAt = now();
            $snapshot = $documents->draft($current);
            $snapshot['doc']['date'] = BusinessTime::format($submittedAt);
            $snapshot['signature'] = [
                'name' => $input['signature'], 'signed_at' => BusinessTime::format($submittedAt),
                'signed_at_utc' => $submittedAt->toIso8601String(), 'user_id' => $owner->id,
                'verified_email' => $current->customer->user->email, 'email_verified_at' => $current->customer->user->email_verified_at->toIso8601String(),
                'method' => 'Typed signature with explicit consent and password confirmation',
                'review_token_sha256' => hash('sha256', $current->review_token), 'consent_accepted' => true,
            ];
            $pdf = $documents->render($snapshot);
            $current->update([
                'status' => 'submitted', 'submitted_at' => $submittedAt, 'signed_snapshot' => $snapshot,
                'signed_pdf' => base64_encode($pdf), 'pdf_sha256' => hash('sha256', $pdf),
                'draft_details' => null, 'review_token' => null,
            ]);
        }, 3);
    }

    public function review(CreditApplication $application, User $staff, array $decision): void
    {
        DB::transaction(function () use ($application, $staff, $decision) {
            $current = CreditApplication::lockForUpdate()->findOrFail($application->id);
            if ($current->status !== 'submitted' || ! $current->submitted_at) {
                $this->fail('decision', 'This application is not awaiting review.');
            }
            if ($decision['status'] === 'approved') {
                app(CreditApplicationDocuments::class)->signedBytes($current);
            }
            $current->update([
                'status' => $decision['status'], 'reviewed_by' => $staff->id, 'reviewer_name' => $staff->name,
                'reviewed_at' => now(), 'review_note' => $decision['review_note'] ?? null,
            ]);
        }, 3);
    }

    private function lockDraft(CreditApplication $application, User $owner): CreditApplication
    {
        GeneralSetting::lockForUpdate()->findOrFail(1);
        $owner = User::lockForUpdate()->findOrFail($owner->id);
        $current = CreditApplication::with(['customer', 'template'])->lockForUpdate()->findOrFail($application->id);
        abort_unless($owner->isCustomer() && $owner->is_active && $owner->email_verified_at && ! $owner->businessApprovalPending()
            && $current->customer->user_id === $owner->id, 404);
        $current->customer->setRelation('user', $owner);
        if ($current->status !== 'awaiting_signature' || ! $current->template->published_at
            || $current->template->retired_at || ! $current->template->legal_approval_reference) {
            $this->fail('application', 'This version is unavailable for signing. Return to Credit Applications for the current form.');
        }

        return $current;
    }

    private function fail(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}
