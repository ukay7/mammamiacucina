<?php

namespace App\Services;

use App\Models\{Customer, PosDraft, Product, User};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Storage, Validator};
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PosDrafts
{
    private function fail(string $message): never { throw ValidationException::withMessages(['draft'=>$message]); }

    public function save(array $input, ?UploadedFile $receipt, User $actor): PosDraft
    {
        abort_unless($actor->isSuper(),403);
        try { $payload=json_decode($input['payload'],true,32,JSON_THROW_ON_ERROR); }
        catch (\Throwable) { $this->fail('The draft could not be read. Please try again.'); }
        $rules=[
            'customer_id'=>'required|integer|exists:customers,id','items'=>'required|array|min:1|max:100','items.*'=>'required|array:id,quantity,price_override_cents,manual_discount_bps',
            'items.*.id'=>'required|integer|distinct|exists:products,id','items.*.quantity'=>'required|integer|min:1|max:9999',
            'fulfillment'=>'required|in:pickup,delivery','stage'=>'required|in:cart,checkout','product_search'=>'nullable|string|max:255',
            'product_category'=>'nullable|integer|min:1',
            'checkout'=>'required|array:phone,notes,payment_method,payment_status,delivery_service,order_channel',
            'checkout.phone'=>'nullable|string|max:40','checkout.notes'=>'nullable|string|max:2000',
            'checkout.payment_method'=>'required|in:cash,etransfer,cheque,credit','checkout.payment_status'=>'required|in:paid,unpaid',
            'checkout.delivery_service'=>'nullable|string|max:20',
            'checkout.order_channel'=>['nullable',Rule::in(array_keys(\App\Models\Order::POS_CHANNELS))],
            'address'=>'required|array:type,alternate','address.type'=>'required|in:company,alternate','address.alternate'=>'present|array:address,city,province,postal_code,country',
            'schedule'=>'required|array:date,time,reference','schedule.date'=>'nullable|date_format:Y-m-d','schedule.time'=>'nullable|date_format:H:i',
            'schedule.reference'=>'nullable|integer|min:0',
        ] + PosSale::adjustmentRules();
        foreach (['address'=>255,'city'=>100,'province'=>100,'postal_code'=>30,'country'=>100] as $field=>$length) $rules['address.alternate.'.$field]='nullable|string|max:'.$length;
        $payload=Validator::make(is_array($payload)?$payload:[], $rules)->validate();
        $hash=hash('sha256',json_encode($payload).'|'.($receipt?hash_file('sha256',$receipt->getRealPath()):'').'|'.(int)($input['remove_receipt']??false));
        $newPath=null;$oldPath=null;
        try {
            $result=DB::transaction(function () use ($input,$receipt,$actor,$payload,$hash,&$newPath,&$oldPath) {
                $draft=isset($input['id']) ? PosDraft::whereKey($input['id'])->lockForUpdate()->first()
                    : PosDraft::withTrashed()->where('client_key',$input['client_key'])->lockForUpdate()->first();
                if (isset($input['id']) && !$draft) $this->fail('This draft is no longer available.');
                if ($draft) {
                    if ($draft->trashed() || $draft->order_id) $this->fail('This draft has already been placed or deleted. Start a new sale.');
                    if ($draft->client_key !== $input['client_key']) $this->fail('Invalid draft reference.');
                    // A retried request whose response was lost must not create another draft.
                    if ($draft->request_hash===$hash && $draft->updated_by===$actor->id &&
                        (!isset($input['id']) || $draft->revision===(int)$input['revision']+1)) return $draft;
                    if (!isset($input['id']) || $draft->revision!==(int)$input['revision']) $this->fail('This draft changed in another window. Reopen it from Draft Orders before saving.');
                }
                $customer=Customer::findOrFail($payload['customer_id']);
                $products=Product::with(['inventory','media'=>fn($q)=>$q->coverImage()])->whereIn('id',array_column($payload['items'],'id'))->get()->keyBy('id');
                $snapshot=$payload;
                $snapshot['items']=array_map(function ($line) use ($products,$customer) {
                    $p=$products[$line['id']]??null;
                    if (!$p) $this->fail('A product was removed. Review the cart before saving.');
                    return ['id'=>$p->id,'quantity'=>(int)$line['quantity'],'name'=>$p->premium_marketing_name,
                        'subtitle'=>$p->italian_subtitle,'source_name'=>$p->original_description,'barcode'=>$p->barcode_number,
                        'code'=>$p->product_code,'active'=>$p->is_active,'unit_cents'=>app(CustomerTerms::class)->price($p,$customer)['unit_cents'],
                        'stock'=>null,'uom'=>$p->uom,'image'=>($media=$p->media->first())?route('admin.pos.media',[$p,$media]):null]
                        + array_intersect_key($line, array_flip(['price_override_cents', 'manual_discount_bps']));
                },$payload['items']);
                $values=['customer_id'=>$customer->id,'customer_label'=>mb_substr(trim(($customer->account_type==='business' && $customer->business_name?$customer->business_name.' · ':'').$customer->name),0,255),
                    'item_count'=>array_sum(array_column($payload['items'],'quantity')),'snapshot'=>$snapshot,'request_hash'=>$hash,'updated_by'=>$actor->id];
                if ($receipt) {
                    // Reuse the same file if a database deadlock retries this transaction.
                    $newPath ??= $receipt->store('pos-drafts','local');
                    if (!$newPath) throw new \RuntimeException('Unable to save draft receipt.');
                    $values+=['receipt_path'=>$newPath,'receipt_name'=>mb_substr(preg_replace('/[^\pL\pN._ -]/u','_',basename($receipt->getClientOriginalName())),0,200) ?: 'receipt','receipt_mime'=>$receipt->getMimeType()];
                } elseif ($input['remove_receipt']??false) $values+=['receipt_path'=>null,'receipt_name'=>null,'receipt_mime'=>null];
                if ($draft) {
                    if (array_key_exists('receipt_path',$values)) $oldPath=$draft->receipt_path;
                    $draft->update($values+['revision'=>$draft->revision+1]);
                } else $draft=PosDraft::create($values+['client_key'=>$input['client_key'],'created_by'=>$actor->id,'revision'=>0]);
                return $draft;
            },3);
        } catch (\Throwable $e) {
            if ($newPath) Storage::disk('local')->delete($newPath);
            // The unique client key also covers simultaneous retries of an initial save.
            if ($e instanceof \Illuminate\Database\QueryException && !isset($input['id'])) {
                $existing=PosDraft::where('client_key',$input['client_key'])->whereNull('order_id')->first();
                if ($existing && $existing->request_hash===$hash && $existing->updated_by===$actor->id) return $existing;
            }
            throw $e;
        }
        if ($oldPath && $oldPath!==$newPath) Storage::disk('local')->delete($oldPath);
        if ($newPath && $result->receipt_path!==$newPath) Storage::disk('local')->delete($newPath);
        return $result;
    }
}
