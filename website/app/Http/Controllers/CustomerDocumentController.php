<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\{CustomerDocuments, CustomerInvoicing, DocumentTheme, InvoicePayments, ReceivableAging};
use App\Support\BusinessTime;
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

    public function statement(Request $request, InvoicePayments $payments, ReceivableAging $aging, DocumentTheme $theme)
    {
        $customer = $request->user()->customerRecord();
        $asOf = BusinessTime::now();
        $rows = [];
        foreach ($payments->invoices($customer)->oldest('issued_at')->oldest('id')->lazy(200) as $invoice) {
            $balance = $aging->invoiceBalance($invoice);
            if ($balance > 0) {
                $rows[] = [
                    'invoice' => $invoice->label,
                    'order' => $invoice->order->number,
                    'issued' => BusinessTime::format($invoice->issued_at, 'd M Y'),
                    'due' => $invoice->paymentDueDate(),
                    'balance' => $balance,
                ];
            }
        }
        $brand = $theme->brand();
        $doc = ['brand' => $brand, 'title' => 'Statement of Account', 'number' => null, 'date' => $asOf->format('Y-m-d H:i:s T')];
        $methods = $theme->remittance($brand);
        $total = array_sum(array_column($rows, 'balance'));

        return $this->renderDocument('documents.customer-statement', compact('customer', 'doc', 'rows', 'total', 'methods'),
            'account-statement-'.$customer->id.'-'.$asOf->toDateString(), true);
    }

    public function adminFile(Request $request, Customer $customer, string $kind, int $document, CustomerDocuments $documents)
    {
        return $this->show($request, $customer, $kind, $document, $documents);
    }

    public function accountingFile(Request $request, Customer $customer, string $kind, int $document, CustomerDocuments $documents)
    {
        return $this->show($request, $customer, $kind, $document, $documents, accounting: true);
    }

    private function show(Request $request, Customer $customer, string $kind, int $id, CustomerDocuments $documents, bool $accounting = false)
    {
        $request->validate(['download' => 'nullable|boolean']);
        [$record, $entry] = $documents->resolve($customer, $request->user(), $kind, $id, $accounting);
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
        if ($kind === 'credit-application') {
            return $this->pdfResponse(app(\App\Services\CreditApplicationDocuments::class)->signedBytes($record), strtolower($record->reference).'-signed.pdf', $download);
        }

        $theme = app(DocumentTheme::class);
        if (in_array($kind, ['invoice', 'settlement'])) $record->load('order');
        $backUrl = $request->user()->isCustomer() ? route(match ($entry['group']) {
            'invoices' => 'customer.invoices', 'receipts' => 'customer.receipts', default => 'customer.documents',
        }) : ($accounting ? route('admin.accounting.report', 'documents') : route('admin.customers.show', $customer).'#customer-documents');
        $downloadUrl = $request->url().'?download=1';
        $data = ['pdf' => $download, 'backUrl' => $backUrl, 'downloadUrl' => $downloadUrl];
        $view = 'documents.financial';
        switch ($kind) {
            case 'revision':
                $data += ['revision'=>$record, 'doc'=>['brand'=>$record->brand,'title'=>'Order Adjustment','number'=>$record->number,'date'=>\App\Support\BusinessTime::format($record->created_at)]];
                $view='documents.order-revision'; break;
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
            case 'invoice': $data['doc'] = $theme->invoice($record); break;
            case 'settlement': $data['doc'] = $theme->order($record->order, receipt: $record); break;
            case 'initial-payment':
                $doc = $theme->order($record, $record->revisions()->exists() ? $record->invoice : null);
                $doc['title'] = 'Payment Receipt'; $doc['number'] = 'PAY-'.$record->number;
                $doc['invoice_reference'] = $record->invoice?->label;
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
        return $this->renderDocument($view, $data, $filename, $download);
    }

    private function renderDocument(string $view, array $data, string $filename, bool $download)
    {
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
