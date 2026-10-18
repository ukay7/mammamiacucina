<?php

namespace App\Services;

use App\Models\EmailHistory;
use App\Models\SmtpSetting;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class EmailOutbox
{
    public function deliver(int $id): bool
    {
        $settings = SmtpSetting::find(1);
        if (! $settings?->ready()) {
            return false;
        }
        $entry = DB::transaction(function () use ($id, $settings) {
            $entry = EmailHistory::whereKey($id)->lockForUpdate()->first();
            if (! $entry || ! $entry->event_key || ! in_array($entry->status, ['queued', 'retry', 'sending'])) {
                return null;
            }
            if ($entry->available_at && $entry->available_at->isFuture()) {
                return null;
            }
            if ($entry->status === 'sending' && $entry->started_at?->gt(now()->subMinutes(5))) {
                return null;
            }
            if ($entry->attempts && ($entry->mailer !== 'resend_api' || $entry->created_at->lt(now()->subHours(12)) || $entry->attempts >= 5)) {
                $entry->update(['status' => 'needs_review', 'delivery_note' => 'Delivery outcome needs review. Check provider logs before sending again.']);

                return null;
            }
            // Do not switch providers while retrying an uncertain request.
            if ($entry->attempts && $settings->delivery_method !== 'resend') {
                return null;
            }
            $payload = $entry->payload;
            if ($entry->attempts && ($payload['credential_hash'] ?? '') !== hash('sha256', (string) $settings->api_key)) {
                $entry->update(['status' => 'needs_review', 'delivery_note' => 'Credentials changed after a delivery attempt; check provider logs before retrying.']);

                return null;
            }
            $payload['credential_hash'] = hash('sha256', (string) $settings->api_key);
            $payload['from_address'] ??= $settings->from_address;
            $payload['from_name'] ??= $settings->from_name;
            if (! empty($payload['document']) && ! isset($payload['attachment'])) {
                $options = new Options(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'defaultFont' => 'DejaVu Sans', 'defaultMediaType' => 'print']);
                $pdf = new Dompdf($options);
                $pdf->loadHtml(view('emails.order-document', $payload['document'])->render());
                $pdf->setPaper('A4');
                $pdf->render();
                $payload['attachment'] = base64_encode($pdf->output());
                unset($payload['document']);
            }
            if (! $entry->archived_at) {
                $entry->archive = EmailArchive::snapshot($entry->type, $payload['body'], $payload['attachment'] ?? null);
                $entry->archived_at = now();
            }
            $entry->update(['payload' => $payload, 'status' => 'sending', 'started_at' => now(), 'attempts' => $entry->attempts + 1,
                'mailer' => $settings->delivery_method === 'resend' ? 'resend_api' : 'configured_smtp']);

            return $entry;
        });
        if (! $entry) {
            return false;
        }
        try {
            $payload = $entry->payload;
            $attachments = isset($payload['attachment']) ? [['filename' => 'order-'.$entry->id.'.pdf', 'content' => $payload['attachment']]] : [];
            $message = (new Email)->to($entry->recipient)->subject($entry->subject)->from(new Address($payload['from_address'], $payload['from_name']));
            if ($settings->delivery_method === 'resend') {
                $messageId = app(ResendEmail::class)->send($settings, $message, $payload['body'], $payload['idempotency_key'], $attachments);
                $accepted = true;
            } else {
                app(SmtpConfiguration::class)->apply();
                app('mail.manager')->forgetMailers();
                $sent = Mail::mailer('configured_smtp')->raw($payload['body'], function ($m) use ($entry, $payload, $attachments) {
                    $m->to($entry->recipient)->subject($entry->subject)->from($payload['from_address'], $payload['from_name']);
                    foreach ($attachments as $a) {
                        $m->attachData(base64_decode($a['content']), $a['filename'], ['mime' => 'application/pdf']);
                    }
                });
                $messageId = $sent?->getMessageId();
                $accepted = (bool) $sent;
            }
            $entry->update(['status' => $accepted ? 'accepted' : 'needs_review', 'message_id' => $messageId, 'sent_at' => $accepted ? now() : null,
                'payload' => $accepted ? null : $payload, 'delivery_note' => $accepted ? 'Provider accepted; inbox delivery is not guaranteed.' : 'Provider did not confirm acceptance.']);

            return $accepted;
        } catch (\Throwable $e) {
            $retry = $entry->mailer === 'resend_api' && $entry->attempts < 5 && $entry->created_at->gt(now()->subHours(12));
            $entry->update(['status' => $retry ? 'retry' : 'needs_review', 'available_at' => now()->addMinutes(2 ** $entry->attempts),
                'delivery_note' => $retry ? 'Provider request failed; retry scheduled with the same idempotency key.' : 'Delivery failed or outcome uncertain. Check provider logs before retrying.']);

            return false;
        }
    }
}
