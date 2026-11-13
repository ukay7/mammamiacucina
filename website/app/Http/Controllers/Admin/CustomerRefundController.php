<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Customer, CustomerRefund, CustomerRefundLine, OrderCredit};
use App\Services\{CustomerRefunds, DocumentTheme};
use App\Support\BusinessTime;
use Dompdf\{Dompdf, Options};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CustomerRefundController extends Controller
{
    private function belongs(Customer $customer, CustomerRefund $refund, ?CustomerRefundLine $line = null): void
    {
        abort_unless((int)$refund->customer_id === (int)$customer->id && (!$line || (int)$line->customer_refund_id === (int)$refund->id), 404);
    }

    public function create(Request $request, Customer $customer, CustomerRefunds $service)
    {
        $request->validate(['order_id'=>'nullable|integer']);
        $order = $request->filled('order_id') ? $customer->orders()->findOrFail($request->integer('order_id')) : null;
        $account = $order ? $service->account($order) : null;
        return view('admin.customers.add-refund', compact('customer','order','account'));
    }

    public function orders(Request $request, Customer $customer, CustomerRefunds $service)
    {
        $data = $request->validate(['q'=>'nullable|string|max:100','page'=>'nullable|integer|min:1']);
        $orders = $customer->orders()->whereIn('status',['delivered','completed'])->where('currency','CAD')
            ->with('payment','settlements')->when($data['q'] ?? '', fn($q,$term)=>$q->where('number','like','%'.$term.'%'))
            ->orderByDesc('id')->paginate(20);
        return response()->json(['results'=>$orders->map(function($order) use($service) {
            $a = $service->account($order);
            return ['id'=>$order->id,'text'=>mb_strtoupper($order->number).' · Refundable CAD '.number_format($a['maximum_cents']/100,2), 'account'=>$a];
        }), 'pagination'=>['more'=>$orders->hasMorePages()]]);
    }

    private function input(Request $request): array
    {
        $request->validate(['lines'=>'required|array|min:1|max:10','lines.*'=>'required|array','lines.*.proof'=>'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120']);
        $data = $request->except('lines'); $data['lines'] = $request->input('lines'); $bytes = 0;
        foreach ($data['lines'] as $i => &$line) {
            $file = $request->file("lines.$i.proof"); $bytes += $file?->getSize() ?? 0;
            $line['proof_hash'] = $file ? hash_file('sha256',$file->getRealPath()) : null;
            $line['proof_name'] = $file ? mb_substr($file->getClientOriginalName(),0,255) : null;
        }
        if ($bytes > 20 * 1024 * 1024) throw ValidationException::withMessages(['lines'=>'Keep all attachments together under 20 MB.']);
        return $data;
    }

    public function preview(Request $request, Customer $customer, CustomerRefunds $service)
    {
        return response()->json($service->preview($customer,$this->input($request),$request->user()));
    }

    public function store(Request $request, Customer $customer, CustomerRefunds $service)
    {
        $data = $this->input($request); $stored = []; $paths = null;
        try {
            $refund = $service->create($customer,$data,$request->user(),function() use($request,$data,&$stored,&$paths) {
                if ($paths !== null) return $paths;
                $paths = [];
                foreach ($data['lines'] as $i=>$line) if ($file=$request->file("lines.$i.proof")) {
                    $path = $file->store('customer-refund-proofs','local');
                    if (!$path) throw ValidationException::withMessages(['proof'=>'Unable to save the attachment. Nothing was recorded.']);
                    $stored[] = $path; $paths[$line['key']] = $path;
                }
                return $paths;
            });
        } catch (\Throwable $e) { Storage::disk('local')->delete($stored); throw $e; }
        return response()->json(['redirect'=>route('admin.customers.refunds.show',[$customer,$refund])]);
    }

    public function show(Customer $customer, CustomerRefund $refund, CustomerRefunds $service)
    {
        $this->belongs($customer,$refund); $refund->load('lines.credit','events.author','order','author');
        $account = $service->account($refund->order);
        return view('admin.customers.refund',compact('customer','refund','account'));
    }

    public function proof(Customer $customer, CustomerRefund $refund, CustomerRefundLine $line)
    {
        $this->belongs($customer,$refund,$line);
        abort_unless($line->proof_path && Storage::disk('local')->exists($line->proof_path),404);
        return response()->file(Storage::disk('local')->path($line->proof_path), ['Cache-Control'=>'private, no-store',
            'X-Content-Type-Options'=>'nosniff','Content-Security-Policy'=>"default-src 'none'; sandbox"])->setPrivate();
    }

    public function actionPreview(Request $request, Customer $customer, CustomerRefund $refund, CustomerRefundLine $line, CustomerRefunds $service)
    {
        $this->belongs($customer,$refund,$line);
        return response()->json($service->previewAction($line,$request->all(),$request->user()));
    }

    public function action(Request $request, Customer $customer, CustomerRefund $refund, CustomerRefundLine $line, CustomerRefunds $service)
    {
        $this->belongs($customer,$refund,$line); $service->act($line,$request->all(),$request->user());
        return response()->json(['redirect'=>route('admin.customers.refunds.show',[$customer,$refund])]);
    }

    public function document(Request $request, Customer $customer, CustomerRefund $refund)
    {
        $this->belongs($customer,$refund);
        $data = $request->validate(['version'=>'nullable|integer|min:1','pdf'=>'nullable|boolean']);
        $event = $refund->events()->when(isset($data['version']),fn($q)=>$q->whereKey($data['version']))->reorder()->latest('id')->firstOrFail();
        $snapshot = $event->snapshot;
        $doc = ['brand'=>$refund->snapshot['brand'],'title'=>$snapshot['confirmed_cents'] ? 'Refund Confirmation' : 'Refund Cheque Record',
            'number'=>$refund->number,'date'=>BusinessTime::format($event->created_at,'d M Y H:i T')];
        $pdf = $request->boolean('pdf');
        return $this->render(view('documents.customer-refund',compact('customer','refund','event','snapshot','doc','pdf')),$pdf,$refund->number.'-v'.$event->id);
    }

    public function credit(Request $request, Customer $customer, CustomerRefund $refund, OrderCredit $credit)
    {
        $this->belongs($customer,$refund);
        abort_unless((int)$credit->customer_id === (int)$customer->id && (int)$credit->refundLine->customer_refund_id === (int)$refund->id,404);
        $request->validate(['pdf'=>'nullable|boolean']);
        $doc = ['brand'=>$credit->snapshot['brand'],'title'=>'Credit Note','number'=>$credit->number,
            'date'=>$credit->snapshot['refund_date']]; $pdf = $request->boolean('pdf');
        return $this->render(view('documents.order-credit',compact('customer','refund','credit','doc','pdf')),$pdf,$credit->number);
    }

    private function render($view, bool $pdf, string $filename)
    {
        if (!$pdf) return $view;
        abort_unless(extension_loaded('gd'),503,'PHP GD must be enabled before rendering document logos.');
        $renderer = new Dompdf(new Options(['isRemoteEnabled'=>false,'isPhpEnabled'=>false,'defaultFont'=>'DejaVu Sans','defaultMediaType'=>'print']));
        $renderer->loadHtml($view->render()); $renderer->setPaper('A4','portrait'); $renderer->render();
        return response($renderer->output(),200,['Content-Type'=>'application/pdf','Content-Disposition'=>'attachment; filename="'.$filename.'.pdf"','Cache-Control'=>'private, no-store']);
    }
}
