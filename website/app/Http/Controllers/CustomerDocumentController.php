<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\{CustomerDocuments, CustomerInvoicing, DocumentTheme};
use Dompdf\{Dompdf, Options};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;

class CustomerDocumentController extends Controller
{
    public function index(Request $request, CustomerDocuments $documents)
    {
        $customer = $request->user()->customerRecord();
        return view('customer.documents', ['customer' => $customer, 'documents' => $documents->listing($customer, $request->user(), $request)]);
    }

    public function customerFile(Request $request, string $kind, int $document, CustomerDocuments $documents)
    {
        return $this->show($request, $request->user()->customerRecord(), $kind, $document, $documents);
    }

    public function adminFile(Request $request, Customer $customer, string $kind, int $document, CustomerDocuments $documents)
    {
        return $this->show($request, $customer, $kind, $document, $documents);
    }

    private function show(Request $request, Customer $customer, string $kind, int $id, CustomerDocuments $documents)
    {
        $request->validate(['download' => 'nullable|boolean']);
        [$record, $entry] = $documents->resolve($customer, $request->user(), $kind, $id);
        $download = $request->boolean('download');
        $filename = Str::slug($entry['name']) ?: 'document-'.$id;
        if ($path = $documents->filePath($kind, $record)) {
            $disk = Storage::disk('local');
            abort_unless($disk->exists($path), 404);
            if ($kind === 'account-proof' && $record->proof_name) $filename = Str::slug(pathinfo($record->proof_name, PATHINFO_FILENAME)) ?: 'document-'.$id;
            $filename .= '.'.(pathinfo($path, PATHINFO_EXTENSION) ?: 'bin');
            return response()->file($disk->path($path), $this->headers() + ['Content-Security-Policy' => "default-src 'none'; sandbox"])
                ->setContentDisposition($download ? 'attachment' : 'inline', $filename)->setPrivate();
        }
        if ($kind === 'email-copy') {
            $bytes = base64_decode($record->archive['attachment'] ?? '', true);
            abort_unless($bytes && str_starts_with($bytes, '%PDF-'), 404);
            return $this->pdfResponse($bytes, $filename.'.pdf', $download);
        }

        $theme = app(DocumentTheme::class);
        if (in_array($kind, ['invoice', 'settlement'])) $record->load('order');
        $backUrl = $request->user()->isCustomer() ? route('customer.documents') : route('admin.customers.show', $customer).'#customer-documents';
        $downloadUrl = $request->url().'?download=1';
        $data = ['pdf' => $download, 'backUrl' => $backUrl, 'downloadUrl' => $downloadUrl];
        $view = 'documents.financial';
        switch ($kind) {
            case 'refund':
                $record->load('refund'); // The document listing deliberately omits large snapshots.
                $refund=$record->refund; $snapshot=$record->snapshot;
                $data += ['customer'=>$customer,'refund'=>$refund,'event'=>$record,'snapshot'=>$snapshot,
                    'doc'=>['brand'=>$refund->snapshot['brand'],'title'=>$snapshot['confirmed_cents']?'Refund Confirmation':'Refund Cheque Record','number'=>$refund->number,'date'=>\App\Support\BusinessTime::format($record->created_at)]];
                $view='documents.customer-refund'; break;
            case 'credit-note':
                $data += ['customer'=>$customer,'refund'=>$record->refundLine->refund,'credit'=>$record,
                    'doc'=>['brand'=>$record->snapshot['brand'],'title'=>'Credit Note','number'=>$record->number,'date'=>$record->snapshot['refund_date']]];
                $view='documents.order-credit'; break;
            case 'order': $data['doc'] = $theme->order($record); break;
            case 'invoice': $data['doc'] = $theme->order($record->order, $record); break;
            case 'settlement': $data['doc'] = $theme->order($record->order, receipt: $record); break;
            case 'initial-payment':
                $doc = $theme->order($record);
                $doc['title'] = 'Payment Receipt'; $doc['number'] = 'PAY-'.$record->number;
                $doc['date'] = \App\Support\BusinessTime::format($entry['date']);
                $doc['totals'] = ['This payment' => '$'.number_format($record->collected_cents / 100, 2)] + $doc['totals'];
                $doc['payment'] = ucfirst($record->original_payment_method ?: $record->payment_method).' / Original recorded collection';
                $doc['notice'] .= ' Original receipt retained. Any subsequent payment corrections or reversals are recorded separately; balances shown are current.';
                $data['doc'] = $doc; break;
            case 'account-receipt':
                $snapshot = $record->snapshot;
                $title = in_array($record->action, ['reverse', 'bounce', 'cancel']) ? 'Payment Adjustment Record'
                    : ($snapshot['confirmed_cents'] ? 'Customer Payment Receipt' : 'Cheque Acknowledgement');
                $data += ['customer' => $customer, 'receipt' => $record->receipt, 'event' => $record, 'snapshot' => $snapshot,
                    'current' => app(CustomerInvoicing::class)->balances($customer),
                    'doc' => ['brand' => $theme->brand(), 'title' => $title, 'number' => $record->receipt->number, 'date' => \App\Support\BusinessTime::format($record->created_at)]];
                $view = 'documents.customer-receipt'; break;
            case 'quotation': $data['quotation'] = $record; $view = 'admin.quotations.print'; break;
        }
        $html = view($view, $data)->render();
        if (! $download) return response($html)->withHeaders($this->headers());
        abort_unless(extension_loaded('gd'), 503, 'PHP GD must be enabled before rendering document logos.');
        $pdf = new Dompdf(new Options(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'defaultFont' => 'DejaVu Sans', 'defaultMediaType' => 'print']));
        $pdf->loadHtml($html); $pdf->setPaper('A4'); $pdf->render();
        return $this->pdfResponse($pdf->output(), $filename.'.pdf', true);
    }

    private function headers(): array
    {
        return ['Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff'];
    }

    private function pdfResponse(string $bytes, string $filename, bool $download)
    {
        return response($bytes)->withHeaders($this->headers() + ['Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition($download ? 'attachment' : 'inline', $filename)]);
    }
}
