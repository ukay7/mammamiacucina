<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerReceipt;
use App\Models\CustomerReceiptLine;
use App\Services\CustomerInvoicing;
use App\Services\CustomerReceipts;
use App\Services\DocumentTheme;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CustomerReceiptController extends Controller
{
    private function belongs(Customer $customer, CustomerReceipt $receipt, ?CustomerReceiptLine $line = null): void
    {
        abort_unless((int) $receipt->customer_id === (int) $customer->id && (! $line || (int) $line->customer_receipt_id === (int) $receipt->id), 404);
    }

    public function create(Customer $customer, CustomerReceipts $service)
    {
        $account = $service->account($customer);

        return view('admin.customers.receive-payment', compact('customer', 'account'));
    }

    private function input(Request $request): array
    {
        $request->validate(['lines' => 'required|array|min:1|max:10', 'lines.*' => 'required|array', 'lines.*.proof' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120']);
        $data = $request->except('lines');
        $data['lines'] = $request->input('lines');
        $bytes = 0;
        foreach ($data['lines'] as $i => &$line) {
            $file = $request->file("lines.$i.proof");
            $bytes += $file?->getSize() ?? 0;
            // Evidence metadata is derived from the uploaded bytes, never from a
            // client-supplied storage path, filename or hash.
            $line['proof_hash'] = $file ? hash_file('sha256', $file->getRealPath()) : null;
            $line['proof_name'] = $file ? mb_substr($file->getClientOriginalName(), 0, 255) : null;
        }
        unset($line);
        if ($bytes > 20 * 1024 * 1024) {
            throw ValidationException::withMessages(['lines' => 'Keep all attachments together under 20 MB.']);
        }

        return $data;
    }

    public function preview(Request $request, Customer $customer, CustomerReceipts $service)
    {
        return response()->json($service->preview($customer, $this->input($request), $request->user()));
    }

    public function store(Request $request, Customer $customer, CustomerReceipts $service)
    {
        $data = $this->input($request);
        $stored = [];
        $storedPaths = null;
        try {
            $receipt = $service->create($customer, $data, $request->user(), function () use ($request, $data, &$stored, &$storedPaths) {
                // A database deadlock retry reuses the same private files.
                if ($storedPaths !== null) {
                    return $storedPaths;
                }
                $storedPaths = [];
                foreach ($data['lines'] as $i => $line) {
                    if ($file = $request->file("lines.$i.proof")) {
                        $path = $file->store('customer-receipt-proofs', 'local');
                        if (! $path) {
                            throw ValidationException::withMessages(['proof' => 'Unable to save the attachment. Nothing was recorded.']);
                        }
                        $stored[] = $path;
                        $storedPaths[$line['key']] = $path;
                    }
                }

                return $storedPaths;
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($stored);
            throw $e;
        }

        return response()->json(['redirect' => route('admin.customers.receipts.show', [$customer, $receipt])]);
    }

    public function show(Customer $customer, CustomerReceipt $receipt)
    {
        $this->belongs($customer, $receipt);
        $receipt->load('lines', 'events');
        $snapshot = $receipt->events->last()->snapshot;
        $current = app(CustomerInvoicing::class)->balances($customer);

        return view('admin.customers.receipt', compact('customer', 'receipt', 'snapshot', 'current'));
    }

    public function proof(Customer $customer, CustomerReceipt $receipt, CustomerReceiptLine $line)
    {
        $this->belongs($customer, $receipt, $line);
        abort_unless($line->proof_path && Storage::disk('local')->exists($line->proof_path), 404);

        return response()->file(Storage::disk('local')->path($line->proof_path), [
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "default-src 'none'; sandbox",
        ])->setPrivate();
    }

    public function actionPreview(Request $request, Customer $customer, CustomerReceipt $receipt, CustomerReceiptLine $line, CustomerReceipts $service)
    {
        $this->belongs($customer, $receipt, $line);

        return response()->json($service->previewAction($line, $request->all(), $request->user()));
    }

    public function action(Request $request, Customer $customer, CustomerReceipt $receipt, CustomerReceiptLine $line, CustomerReceipts $service)
    {
        $this->belongs($customer, $receipt, $line);
        $service->act($line, $request->all(), $request->user());

        return response()->json(['redirect' => route('admin.customers.receipts.show', [$customer, $receipt])]);
    }

    public function document(Request $request, Customer $customer, CustomerReceipt $receipt, DocumentTheme $theme)
    {
        $this->belongs($customer, $receipt);
        $data = $request->validate(['version' => 'nullable|integer|min:1', 'pdf' => 'nullable|boolean']);
        $event = $receipt->events()->when(isset($data['version']), fn ($q) => $q->whereKey($data['version']))->reorder()->latest('id')->firstOrFail();
        $snapshot = $event->snapshot;
        $title = in_array($event->action, ['reverse', 'bounce', 'cancel']) ? 'Payment Adjustment Record'
            : ($snapshot['confirmed_cents'] ? 'Customer Payment Receipt' : 'Cheque Acknowledgement');
        $doc = ['brand' => $theme->brand(), 'title' => $title, 'number' => $receipt->number,
            'date' => $event->created_at->format('d M Y H:i').' '.config('app.timezone')];
        $current = app(CustomerInvoicing::class)->balances($customer);
        $pdf = $request->boolean('pdf');
        $view = view('documents.customer-receipt', compact('customer', 'receipt', 'event', 'snapshot', 'doc', 'current', 'pdf'));
        if (! $pdf) {
            return $view;
        }
        abort_unless(extension_loaded('gd'), 503, 'PHP GD must be enabled before rendering document logos.');
        $renderer = new Dompdf(new Options(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'defaultFont' => 'DejaVu Sans', 'defaultMediaType' => 'print']));
        $renderer->loadHtml($view->render());
        $renderer->setPaper('A4', 'portrait');
        $renderer->render();

        return response($renderer->output(), 200, ['Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$receipt->number.'-v'.$event->id.'.pdf"']);
    }
}
