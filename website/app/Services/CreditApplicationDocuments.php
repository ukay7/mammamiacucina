<?php

namespace App\Services;

use App\Models\CreditApplication;
use App\Support\BusinessTime;
use Dompdf\Dompdf;
use Dompdf\Options;

class CreditApplicationDocuments
{
    public function draft(CreditApplication $application): array
    {
        $template = $application->template;
        $draft = $application->draft_details;

        return [
            'doc' => [
                'brand' => $draft['brand'], 'title' => $template->title,
                'number' => $application->reference, 'date' => BusinessTime::format($application->prepared_at),
            ],
            'template' => [
                'version' => $template->version,
                'application_terms' => $template->application_terms,
                'guarantee_terms' => $template->guarantee_terms,
                'consent_statement' => $template->consent_statement,
            ],
            'customer_number' => $application->customer_id, 'details' => $draft['details'],
            'signature' => null,
        ];
    }

    public function render(array $snapshot): string
    {
        abort_unless(extension_loaded('gd'), 503, 'PDF rendering is temporarily unavailable. Please contact us.');
        $pdf = new Dompdf(new Options(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'defaultFont' => 'DejaVu Sans', 'defaultMediaType' => 'print']));
        $pdf->loadHtml(view('documents.credit-application', $snapshot)->render());
        $pdf->setPaper('A4');
        $pdf->render();

        return $pdf->output();
    }

    public function signedBytes(CreditApplication $application): string
    {
        $bytes = base64_decode($application->signed_pdf ?? '', true);
        abort_unless($bytes && $application->pdf_sha256 && hash_equals($application->pdf_sha256, hash('sha256', $bytes)), 409,
            'The stored signature document could not be verified.');

        return $bytes;
    }
}
