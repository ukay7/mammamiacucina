<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Category, Customer, PricingList};
use App\Services\PricingLists;
use App\Support\BusinessTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PricingListController extends Controller
{
    public function index(Request $request)
    {
        $filters=$request->validate(['search'=>'nullable|string|max:200','tier'=>'nullable|in:individual,business',
            'from'=>'nullable|date_format:Y-m-d','to'=>'nullable|date_format:Y-m-d|after_or_equal:from']);
        $lists=PricingList::query()
            ->when($filters['search']??null,function($q,$term){
                $q->where(fn($q)=>$q->where('title','like','%'.$term.'%')->orWhere('recipient_name','like','%'.$term.'%')
                    ->orWhere('number',preg_match('/^(?:PL-)?0*(\d+)$/i',$term,$m)?'PL-'.str_pad($m[1],5,'0',STR_PAD_LEFT):$term));
            })->when($filters['tier']??null,fn($q,$tier)=>$q->where('pricing_tier',$tier))
            ->when($filters['from']??null,fn($q,$date)=>$q->whereDate('effective_date','>=',$date))
            ->when($filters['to']??null,fn($q,$date)=>$q->whereDate('effective_date','<=',$date))
            ->latest('updated_at')->paginate(20)->withQueryString();
        return view('admin.pricing-lists.index',compact('lists','filters'));
    }

    public function create(PricingLists $service) { return $this->editor(new PricingList(),$service); }
    public function edit(PricingList $pricingList,PricingLists $service) { return $this->editor($pricingList,$service); }

    private function editor(PricingList $list,PricingLists $service)
    {
        $catalog=collect($service->catalog())->keyBy('product_id');
        foreach ($list->items??[] as $item) $catalog->put($item['product_id'],$item);
        $customers=Customer::orderBy('name')->get(['id','name','business_name']);
        $initial=['title'=>$list->title??'Price List','pricing_tier'=>$list->pricing_tier??'business',
            'effective_date'=>$list->effective_date?->format('Y-m-d')??BusinessTime::now()->toDateString(),
            'valid_until'=>$list->valid_until?->format('Y-m-d')??'', 'recipient_name'=>$list->recipient_name??'',
            'customer_id'=>$list->customer_id,'show_images'=>$list->show_images??true,'notes'=>$list->notes??'',
            'revision'=>$list->revision,'filters'=>$list->filters??[], 'selected'=>array_column($list->items??[],'product_id')];
        $config=['catalog'=>$catalog->values()->all(),'categories'=>Category::orderBy('name')->get(['id','name']),'initial'=>$initial,
            'draft_key'=>$list->exists?null:(string)\Illuminate\Support\Str::uuid(),
            'preview_url'=>$list->exists?route('admin.pricing-lists.preview',$list):route('admin.pricing-lists.preview-new'),
            'save_url'=>$list->exists?route('admin.pricing-lists.update',$list):route('admin.pricing-lists.store'),
            'method'=>$list->exists?'PUT':'POST','csrf'=>csrf_token()];
        return view('admin.pricing-lists.edit',compact('list','customers','config'));
    }

    public function previewNew(Request $request,PricingLists $service) { return $this->previewResponse($request,$service,null); }
    public function preview(Request $request,PricingLists $service,PricingList $pricingList) { return $this->previewResponse($request,$service,$pricingList); }

    private function previewResponse(Request $request,PricingLists $service,?PricingList $existing)
    {
        [$token,$list]=$service->preview($request,$existing);
        if ($existing) $list->id=$existing->id;
        return response()->json(['token'=>$token,'number'=>$list->number,'count'=>count($list->items),
            'html'=>view('documents.pricing-list',['list'=>$list,'pdf'=>false])->render()])
            ->header('Cache-Control','private, no-store, no-transform');
    }

    public function store(Request $request,PricingLists $service) { return $this->saved($service->save($request,null)); }
    public function update(Request $request,PricingLists $service,PricingList $pricingList) { return $this->saved($service->save($request,$pricingList)); }

    private function saved(PricingList $list)
    {
        return response()->json(['show_url'=>route('admin.pricing-lists.show',$list),'pdf_url'=>route('admin.pricing-lists.pdf',$list)]);
    }

    public function show(PricingList $pricingList) { return view('admin.pricing-lists.show',['list'=>$pricingList]); }
    public function document(PricingList $pricingList)
    {
        // Preserve email addresses in the sandboxed preview without relying on injected scripts.
        return response()->view('documents.pricing-list',['list'=>$pricingList,'pdf'=>false])
            ->header('Cache-Control','private, no-store, no-transform');
    }

    public function pdf(PricingList $pricingList)
    {
        abort_unless(extension_loaded('gd'),503,'PHP GD is required to render document logos.');
        $renderer=new \Dompdf\Dompdf(new \Dompdf\Options(['isRemoteEnabled'=>false,'isPhpEnabled'=>false,'defaultFont'=>'DejaVu Sans','defaultMediaType'=>'print']));
        $renderer->loadHtml(view('documents.pricing-list',['list'=>$pricingList,'pdf'=>true])->render());
        $renderer->setPaper('A4');
        $renderer->render();
        $renderer->getCanvas()->page_text(440,818,$pricingList->number.' | {PAGE_NUM} / {PAGE_COUNT}',$renderer->getFontMetrics()->getFont('Helvetica'),8,[0.4,0.33,0.27]);
        return response($renderer->output(),200,['Content-Type'=>'application/pdf',
            'Content-Disposition'=>'inline; filename="'.$pricingList->number.'.pdf"','Cache-Control'=>'private, no-store']);
    }

    public function duplicate(Request $request,PricingList $pricingList,PricingLists $service)
    {
        $copy=$service->duplicate($pricingList,$request->user()->id);
        return redirect()->route('admin.pricing-lists.edit',$copy)->with('status','List duplicated. Choose a recipient and adjust the copied prices.');
    }

    public function destroy(Request $request,PricingLists $service,PricingList $pricingList)
    {
        $revision=$request->validate(['revision'=>'required|integer'])['revision'];
        DB::transaction(function()use($pricingList,$revision,$service){
            $list=PricingList::lockForUpdate()->findOrFail($pricingList->id);
            if ($list->revision!==(int)$revision) $service->stale();
            $list->delete();
        });
        return redirect()->route('admin.pricing-lists.index')->with('status','Pricing list deleted.');
    }
}
