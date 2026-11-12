<?php

namespace App\Services;

use App\Models\{Customer, PricingList, PricingListNumber, Product};
use App\Support\NumberDisplay;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Cache, DB};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PricingLists
{
    public function catalog(): array
    {
        $products=Product::with(['category', 'categories', 'pricingDraft',
            'priceReviews'=>fn($q)=>$q->where('status','saved')->orderByDesc('id')->limit(1)])->orderBy('premium_marketing_name')->get();
        $images=app(DocumentProductImages::class)->forItems($products->map(fn($p)=>['product_id'=>$p->id])->all());
        return $products->values()->map(fn($p,$i)=>$this->product($p)+['image'=>$images[$i]??null])->all();
    }

    private function product(Product $p): array
    {
        $rawPieces=$p->pricingDraft?->inputs['pieces_per_carton'] ?? data_get($p->dna,'pieces_per_carton') ?? $p->pieces_per_pack;
        $pieces=is_numeric($rawPieces) && (float)$rawPieces>0 && (float)$rawPieces<=100000 && floor((float)$rawPieces)==(float)$rawPieces ? (int)$rawPieces : null;
        $pack=$pieces ? NumberDisplay::quantity($pieces).((float)$p->unit_weight_g>0 ? ' x '.NumberDisplay::quantity($p->unit_weight_g).' g' : ' pcs')
            : ((float)$p->pack_weight_kg>0 ? NumberDisplay::quantity($p->pack_weight_kg).' kg' : (filled($p->size_diameter_cm)?$p->size_diameter_cm.' cm':($p->uom ?: 'Not specified')));
        // The existing quotation module treats unlabelled catalogue prices as box
        // prices. An explicit saved calculator choice of piece takes precedence.
        $basis=data_get($p->priceReviews->first()?->snapshot,'choice')==='piece' ? 'each' : 'box';
        $defaults=[];
        foreach (['individual'=>$p->total_selling_price_cad, 'business'=>$p->business_selling_price_cad ?? $p->total_selling_price_cad] as $tier=>$price) {
            $value=$price===null?null:(int)round((float)$price*100);
            $defaults[$tier]=$this->pair($basis,$basis==='each'?$value:null,$basis==='box'?$value:null,$pieces);
        }
        return ['product_id'=>$p->id,'name'=>$p->premium_marketing_name,'code'=>$p->qr_code ?: $p->product_code,
            'category'=>$p->category?->name ?: 'Uncategorized','category_id'=>$p->category_id,
            'category_ids'=>$p->categories->pluck('id')->push($p->category_id)->unique()->values()->all(),
            'supplier'=>$p->supplier ?: '', 'active'=>$p->is_active,'pack'=>$pack,'pieces'=>$pieces,
            'defaults'=>$defaults,'price_basis'=>$basis,'business_fallback'=>$p->business_selling_price_cad===null];
    }

    private function pair(string $basis, ?int $each, ?int $box, ?int $pieces): array
    {
        if ($pieces && $basis==='box') $each=$box===null?null:intdiv($box+intdiv($pieces,2),$pieces);
        if ($pieces && $basis==='each') $box=$each===null?null:$each*$pieces;
        if (($box??0)>100000000 || ($each??0)>100000000) throw ValidationException::withMessages(['items'=>'A list price cannot exceed CAD 1,000,000.']);
        return ['each_cents'=>$each,'box_cents'=>$box];
    }

    public function validate(Request $request, ?PricingList $existing): array
    {
        $money=['nullable','regex:/^\d{1,7}(\.\d{1,2})?$/','numeric','min:0','max:1000000'];
        $data=$request->validate([
            'title'=>'required|string|max:150','pricing_tier'=>'required|in:individual,business',
            'effective_date'=>'required|date_format:Y-m-d','valid_until'=>'nullable|date_format:Y-m-d|after_or_equal:effective_date',
            'customer_id'=>'nullable|integer|exists:customers,id','recipient_name'=>'nullable|string|max:255',
            'show_images'=>'required|boolean','notes'=>'nullable|string|max:3000','revision'=>'nullable|integer|min:1',
            'draft_key'=>'nullable|uuid',
            'items'=>'required|array|min:1|max:500','items.*'=>'array:product_id,each_price,box_price,price_basis',
            'items.*.product_id'=>'required|integer|distinct','items.*.each_price'=>$money,'items.*.box_price'=>$money,
            'items.*.price_basis'=>'required|in:box,each,independent',
            'filters'=>'sometimes|array:q,categories,supplier,active,price_state,selection,min_price,max_price,sort',
            'filters.q'=>'nullable|string|max:200','filters.categories'=>'sometimes|array|max:100',
            'filters.categories.*'=>'integer','filters.supplier'=>'nullable|string|max:255',
            'filters.active'=>'nullable|in:all,active,inactive','filters.price_state'=>'nullable|in:all,priced,missing',
            'filters.selection'=>'nullable|in:all,selected,unselected','filters.min_price'=>$money,'filters.max_price'=>$money,
            'filters.sort'=>'nullable|in:name,code,price',
        ]);
        if ($existing && (int)($data['revision']??0)!==$existing->revision) $this->stale();
        $saved=collect($existing?->items??[])->keyBy('product_id');
        $ids=array_column($data['items'],'product_id');
        $products=Product::with(['category','categories','pricingDraft','priceReviews'=>fn($q)=>$q->where('status','saved')->orderByDesc('id')->limit(1)])->whereIn('id',$ids)->get()->keyBy('id');
        $new=array_values(array_filter($data['items'],fn($row)=>!$saved->has($row['product_id'])));
        $images=app(DocumentProductImages::class)->forItems($new);
        $newImages=[]; foreach ($new as $i=>$row) $newImages[$row['product_id']]=$images[$i]??null;
        $items=[];
        foreach ($data['items'] as $row) {
            $id=(int)$row['product_id'];
            $details=$saved->get($id);
            if (!$details) {
                $product=$products->get($id);
                if (!$product) throw ValidationException::withMessages(['items'=>'A selected product no longer exists. Reload the catalogue.']);
                $details=$this->product($product)+['image'=>$newImages[$id]??null];
            }
            $cents=function($value) {
                if ($value===null || $value==='') return null;
                [$whole,$fraction]=array_pad(explode('.',(string)$value,2),2,'');
                return (int)$whole*100+(int)str_pad($fraction,2,'0');
            };
            $prices=$this->pair($row['price_basis'],$cents($row['each_price']??null),$cents($row['box_price']??null),$details['pieces']);
            $items[]=array_merge($details,$prices,['price_basis'=>$row['price_basis']]);
        }
        $sort=$data['filters']['sort']??'name';
        usort($items,function($a,$b)use($sort){
            $category=strnatcasecmp($a['category'],$b['category']);
            if ($category) return $category;
            $compare=$sort==='price'?($a['box_cents']??PHP_INT_MAX)<=>($b['box_cents']??PHP_INT_MAX):strnatcasecmp((string)$a[$sort==='code'?'code':'name'],(string)$b[$sort==='code'?'code':'name']);
            return $compare ?: $a['product_id']<=>$b['product_id'];
        });
        $customerId=$data['customer_id']??null;
        $customer=$customerId?Customer::findOrFail($customerId):null;
        $customerSnapshot=$customer && $existing?->customer_id==$customerId ? $existing->customer_snapshot
            : $customer?->only(['id','name','business_name','email','phone','address','city','province','postal_code','country']);
        return array_intersect_key($data,array_flip(['title','pricing_tier','effective_date','valid_until','notes','show_images']))+
            ['customer_id'=>$customerId,'recipient_name'=>trim($data['recipient_name']??'') ?: ($customer?->business_name ?: $customer?->name),
                'customer_snapshot'=>$customerSnapshot,'brand_snapshot'=>$existing?->brand_snapshot ?? app(DocumentTheme::class)->brand(),
                'items'=>$items,'filters'=>$data['filters']??[]];
    }

    public function preview(Request $request, ?PricingList $existing): array
    {
        $data=$this->validate($request,$existing);
        $reservation=$existing ? null : $this->reserveNumber($request->input('draft_key') ?: (string)Str::uuid(),$request->user()->id);
        if ($reservation?->pricing_list_id) {
            throw ValidationException::withMessages(['preview'=>'This pricing list was already saved as '.$reservation->number.'. Open the saved list to edit it.']);
        }
        $data['number']=$existing?->number ?? $reservation->number;
        $token=(string)Str::uuid();
        Cache::put('pricing-list-preview:'.$token,['user_id'=>$request->user()->id,'list_id'=>$existing?->id,
            'revision'=>$existing?->revision,'reservation_id'=>$reservation?->id,'data'=>$data],now()->addMinutes(20));
        return [$token,new PricingList($data)];
    }

    private function reserveNumber(string $key,int $userId): PricingListNumber
    {
        $reservation=PricingListNumber::firstOrCreate(['draft_key'=>$key],['created_by'=>$userId]);
        if ($reservation->created_by!==$userId) {
            throw ValidationException::withMessages(['draft_key'=>'This draft belongs to another staff member. Start a new pricing list.']);
        }
        return $reservation;
    }

    public function save(Request $request, ?PricingList $existing): PricingList
    {
        $token=$request->validate(['preview_token'=>'required|uuid'])['preview_token'];
        return Cache::lock('pricing-list-save:'.$token,15)->block(5,function()use($request,$existing,$token){
            $key='pricing-list-preview:'.$token; $preview=Cache::get($key);
            if (!$preview || empty($preview['data']['number']) || $preview['user_id']!==$request->user()->id || $preview['list_id']!==$existing?->id) {
                throw ValidationException::withMessages(['preview'=>'This confirmation expired or belongs to another list. Review the list again.']);
            }
            if (isset($preview['saved_id'])) return PricingList::findOrFail($preview['saved_id']);
            $list=DB::transaction(function()use($preview,$existing,$request,$token){
                if ($existing) {
                    $list=PricingList::lockForUpdate()->findOrFail($existing->id);
                    if ($list->revision!==$preview['revision']) $this->stale();
                    $list->update($preview['data']+['revision'=>$list->revision+1,'updated_by'=>$request->user()->id]);
                    return $list;
                }
                $reservation=PricingListNumber::lockForUpdate()->findOrFail($preview['reservation_id']);
                if ($reservation->created_by!==$request->user()->id || $reservation->number!==$preview['data']['number']) {
                    throw ValidationException::withMessages(['preview'=>'Review the pricing list again before saving.']);
                }
                if ($reservation->pricing_list_id) {
                    if ($reservation->confirmed_token===$token) return PricingList::findOrFail($reservation->pricing_list_id);
                    throw ValidationException::withMessages(['preview'=>'This pricing list was already saved as '.$reservation->number.'. Open the saved list to edit it.']);
                }
                $list=PricingList::create($preview['data']+['created_by'=>$request->user()->id,'updated_by'=>$request->user()->id]);
                $reservation->update(['pricing_list_id'=>$list->id,'confirmed_token'=>$token]);
                return $list;
            });
            Cache::put($key,$preview+['saved_id'=>$list->id],now()->addMinutes(20));
            return $list;
        });
    }

    public function duplicate(PricingList $source,int $userId): PricingList
    {
        return DB::transaction(function()use($source,$userId){
            $reservation=$this->reserveNumber((string)Str::uuid(),$userId);
            $copy=$source->replicate(['number','created_by','updated_by','revision']);
            $copy->number=$reservation->number;
            $copy->title=mb_substr($source->title.' (copy)',0,150);
            $copy->created_by=$copy->updated_by=$userId;
            $copy->revision=1;
            $copy->save();
            $reservation->update(['pricing_list_id'=>$copy->id]);
            return $copy;
        });
    }

    public function stale(): never
    {
        throw ValidationException::withMessages(['revision'=>'This pricing list changed. Reload before saving or deleting.']);
    }
}
