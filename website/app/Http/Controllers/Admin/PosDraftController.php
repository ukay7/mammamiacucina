<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PosDraft;
use App\Services\PosDrafts;
use App\Support\BusinessTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Validation\ValidationException;

class PosDraftController extends Controller
{
    public function index(Request $request)
    {
        $filters=$request->validate(['q'=>'nullable|string|max:150','page'=>'nullable|integer|min:1']);
        $rows=PosDraft::whereNull('order_id')->select(['id','customer_id','customer_label','item_count','revision','updated_by','updated_at'])
            ->with(['customer:id,account_type','author:id,name'])->when($filters['q']??null, function ($q,$term) {
                $q->where(fn($q)=>$q->where('customer_label','like','%'.$term.'%')->orWhere('id',(int)preg_replace('/^DRAFT-0*/i','',$term)));
            })->orderByDesc('updated_at')->orderByDesc('id')->paginate(20);
        return response()->json(['rows'=>$rows->map(fn($draft)=>[
            'id'=>$draft->id,'number'=>$draft->number,'customer'=>$draft->customer_label,'account_type'=>$draft->customer?->account_type,
            'item_count'=>$draft->item_count,'revision'=>$draft->revision,'saved_by'=>$draft->author?->name??'Former admin',
            'updated_at'=>BusinessTime::format($draft->updated_at),'url'=>route('admin.pos.drafts.show',$draft),
        ]),'total'=>$rows->total(),'page'=>$rows->currentPage(),'last_page'=>$rows->lastPage()])->header('Cache-Control','private, no-store');
    }

    public function save(Request $request, PosDrafts $drafts)
    {
        $input=$request->validate(['id'=>'nullable|integer|min:1','revision'=>'required|integer|min:0','client_key'=>'required|uuid',
            'payload'=>'required|string|max:80000','receipt'=>'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120','remove_receipt'=>'sometimes|boolean']);
        $draft=$drafts->save($input,$request->file('receipt'),$request->user());
        return response()->json(['id'=>$draft->id,'number'=>$draft->number,'revision'=>$draft->revision]);
    }

    public function show(PosDraft $draft)
    {
        if ($draft->order_id) throw ValidationException::withMessages(['draft'=>'This draft has already been placed as an order.']);
        return response()->json(['id'=>$draft->id,'number'=>$draft->number,'revision'=>$draft->revision,'client_key'=>$draft->client_key,
            'payload'=>$draft->snapshot,'customer'=>$draft->customer?->only(['id','name','email','phone','account_type','business_name','address','city','province','postal_code','country','delivery_address']),
            'receipt'=>$draft->receipt_path ? ['name'=>$draft->receipt_name,'mime'=>$draft->receipt_mime,'url'=>route('admin.pos.drafts.receipt',$draft)] : null,
        ])->header('Cache-Control','private, no-store');
    }

    public function receipt(PosDraft $draft)
    {
        abort_unless($draft->receipt_path && Storage::disk('local')->exists($draft->receipt_path),404);
        return Storage::disk('local')->download($draft->receipt_path,$draft->receipt_name,[
            'Content-Type'=>$draft->receipt_mime,'X-Content-Type-Options'=>'nosniff','Cache-Control'=>'private, no-store']);
    }

    public function destroy(Request $request, PosDraft $draft)
    {
        $revision=$request->validate(['revision'=>'required|integer|min:0'])['revision'];
        DB::transaction(function () use ($draft,$revision) {
            $locked=PosDraft::lockForUpdate()->findOrFail($draft->id);
            if ($locked->order_id || $locked->revision !== (int)$revision) throw ValidationException::withMessages(['draft'=>'This draft changed. Refresh the list before deleting it.']);
            $locked->delete();
        });
        return response()->json(['deleted'=>true]);
    }
}
