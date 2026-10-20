<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\{Product,Role,StockCountItem,StockCountItemLog,StockCountTask,User,Warehouse,WarehouseInventory};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class StockCountController extends Controller {
 public function index(Request $r){
  $manage=$r->user()->hasAdminPermission('stock-counts.view');
  $tasks=StockCountTask::with(['warehouse','assignee'])->withCount(['items','items as counted_items_count'=>fn($q)=>$q->whereNotNull('actual_quantity')])
   ->when(!$manage,fn($q)=>$q->where('assigned_to',$r->user()->id))->latest()->paginate(20);
  return view('admin.stock-counts.index',compact('tasks','manage'));
 }
 public function create(){
  $users=User::where('is_active',true)->whereHas('role',fn($q)=>$q->where('name',Role::WAREHOUSE_USER))->orderBy('name')->get();
  return view('admin.stock-counts.create',['warehouses'=>Warehouse::where('is_active',true)->orderByDesc('is_default')->orderBy('name')->get(),'users'=>$users,'products'=>Product::where('is_active',true)->with(['media'=>fn($q)=>$q->coverImage()])->orderBy('premium_marketing_name')->get(['id','premium_marketing_name','product_code','qr_code'])]);
 }
 public function store(Request $r){
  $d=$r->validate(['scope'=>'required|in:full,specific','warehouse_id'=>'required|exists:warehouses,id','assigned_to'=>'required|exists:users,id','starts_at'=>'nullable|date','due_at'=>'nullable|date|after_or_equal:starts_at','instructions'=>'nullable|string|max:4000','product_ids'=>'nullable|array','product_ids.*'=>'integer|exists:products,id','excluded_product_ids'=>'nullable|array','excluded_product_ids.*'=>'integer|exists:products,id']);
  $assignee=User::with('role')->findOrFail($d['assigned_to']); if(!$assignee->isWarehouseUserType())throw ValidationException::withMessages(['assigned_to'=>'Select an active Warehouse User.']);
  $ids=collect($d['product_ids']??[])->unique();
  if($ids->isEmpty())throw ValidationException::withMessages(['product_ids'=>'Select at least one product for the count.']);
  $task=DB::transaction(function()use($d,$ids,$r){$task=StockCountTask::create(['number'=>'PENDING-'.(string)\Illuminate\Support\Str::uuid(),'scope'=>$d['scope'],'warehouse_id'=>$d['warehouse_id'],'assigned_to'=>$d['assigned_to'],'starts_at'=>$d['starts_at']??null,'due_at'=>$d['due_at']??null,'instructions'=>$d['instructions']??null,'created_by'=>$r->user()->id]);$task->update(['number'=>'CNT-'.str_pad((string)$task->id,4,'0',STR_PAD_LEFT)]);$task->items()->createMany($ids->map(fn($id)=>['product_id'=>$id])->all());return $task;});
  return redirect()->route('admin.stock-counts.show',$task)->with('status','Stock count '.$task->number.' assigned.');
 }
 public function show(Request $r,StockCountTask $stockCount){
  $this->access($r,$stockCount);
  $stockCount->load(['warehouse','assignee','creator'])->loadCount(['items','items as counted_items_count'=>fn($q)=>$q->whereNotNull('actual_quantity')]);
  $rows=in_array((int)$r->input('rows'),[20,50,100],true)?(int)$r->input('rows'):20;
  $search=trim((string)$r->input('q'));
  $items=$stockCount->items()->with(['product.media'=>fn($q)=>$q->coverImage(),'product.warehouseInventories'=>fn($q)=>$q->where('warehouse_id',$stockCount->warehouse_id)])
   ->when($search!=='',fn($q)=>$q->whereHas('product',fn($p)=>$p->where(function($match)use($search){$match->where('premium_marketing_name','like','%'.$search.'%')->orWhere('product_code','like','%'.$search.'%')->orWhere('qr_code','like','%'.$search.'%');})))
   ->orderBy('id')->paginate($rows)->withQueryString();
  return view('admin.stock-counts.show',['task'=>$stockCount,'items'=>$items,'search'=>$search,'rows'=>$rows,'canViewSystem'=>$r->user()->hasAdminPermission('stock-counts.view'),'canCount'=>$stockCount->assigned_to===$r->user()->id&&in_array($stockCount->status,['scheduled','in_progress'],true)]);
 }
 public function save(Request $r,StockCountTask $stockCount,StockCountItem $item){
  $this->countAccess($r,$stockCount);abort_unless($item->stock_count_task_id===$stockCount->id,404);
  $d=$r->validate(['quantity_change'=>'required|numeric|min:-999999999|max:999999999|decimal:0,3','note'=>'nullable|string|max:2000']);
  $change=(int)round((float)$d['quantity_change']*1000);$note=trim((string)($d['note']??''));
  if($change===0)throw ValidationException::withMessages(['quantity_change'=>'Enter a quantity to add or remove.']);
  if($change<0&&$note==='')throw ValidationException::withMessages(['note'=>'Add a reason when removing damaged or unavailable products.']);
  $after=DB::transaction(function()use($r,$stockCount,$item,$change,$note){
   $task=StockCountTask::lockForUpdate()->findOrFail($stockCount->id);
   abort_unless($task->assigned_to===$r->user()->id&&in_array($task->status,['scheduled','in_progress'],true),422,'This count is closed.');
   $locked=StockCountItem::lockForUpdate()->where('stock_count_task_id',$task->id)->findOrFail($item->id);
   $before=(int)round((float)($locked->actual_quantity??0)*1000);$after=$before+$change;
   if($after<0)throw ValidationException::withMessages(['quantity_change'=>'This adjustment would make the counted total negative.']);
   if($after>999999999000)throw ValidationException::withMessages(['quantity_change'=>'The counted total cannot exceed 999,999,999.']);
   $now=now();$locked->update(['actual_quantity'=>$after/1000,'note'=>$note?:null,'counted_at'=>$now]);
   StockCountItemLog::create(['stock_count_item_id'=>$locked->id,'quantity_change'=>$change/1000,'quantity_before'=>$before/1000,'quantity_after'=>$after/1000,'note'=>$note?:null,'created_by'=>$r->user()->id,'created_at'=>$now]);
   $task->update(['status'=>'in_progress','last_saved_at'=>$now]);return $after/1000;
  },3);
  $payload=['message'=>'Count adjustment saved.','quantity'=>$after,'counted'=>$stockCount->items()->whereNotNull('actual_quantity')->count(),'total'=>$stockCount->items()->count()];
  return $r->expectsJson()?response()->json($payload):back()->with('status',$payload['message']);
 }
 public function logs(Request $r,StockCountTask $stockCount,StockCountItem $item){
  $this->access($r,$stockCount);abort_unless($item->stock_count_task_id===$stockCount->id,404);$item->load('product');
  $logs=$item->logs()->with('author')->latest('id')->limit(100)->get();
  return response()->json(['product'=>$item->product->premium_marketing_name,'total'=>(float)($item->actual_quantity??0),'logs'=>$logs->map(fn($log)=>['change'=>(float)$log->quantity_change,'before'=>(float)$log->quantity_before,'after'=>(float)$log->quantity_after,'note'=>$log->note,'user'=>$log->author?->name??'Former user','date'=>$log->created_at?->format('d M Y H:i')])]);
 }
 public function submit(Request $r,StockCountTask $stockCount){$this->countAccess($r,$stockCount);DB::transaction(function()use($stockCount){$task=StockCountTask::lockForUpdate()->findOrFail($stockCount->id);if($task->items()->whereNull('actual_quantity')->exists())throw ValidationException::withMessages(['count'=>'Enter an actual count for every product before submitting.']);$levels=WarehouseInventory::where('warehouse_id',$task->warehouse_id)->whereIn('product_id',$task->items()->pluck('product_id'))->get()->keyBy('product_id');foreach($task->items as $item)$item->update(['system_quantity_snapshot'=>$levels[$item->product_id]->quantity_on_hand??null]);$task->update(['status'=>'submitted','submitted_at'=>now(),'last_saved_at'=>now()]);});return redirect()->route('admin.stock-counts.show',$stockCount)->with('status','Count submitted. The result is now frozen for admin review.');}
 public function cancel(Request $r,StockCountTask $stockCount){abort_unless($r->user()->hasAdminPermission('stock-counts.manage'),403);abort_if($stockCount->status==='submitted',422,'Submitted counts cannot be cancelled.');$stockCount->update(['status'=>'cancelled']);return back()->with('status','Count task cancelled.');}
 public function destroy(Request $r,StockCountTask $stockCount){abort_unless($r->user()->hasAdminPermission('stock-counts.manage'),403);$number=$stockCount->number;$stockCount->delete();return redirect()->route('admin.stock-counts.index')->with('status','Stock count '.$number.' deleted.');}
 private function access(Request $r,StockCountTask $task):void{abort_unless($r->user()->hasAdminPermission('stock-counts.view')||($r->user()->hasAdminPermission('stock-counts.perform')&&$task->assigned_to===$r->user()->id),403);}
 private function countAccess(Request $r,StockCountTask $task):void{abort_unless($r->user()->hasAdminPermission('stock-counts.perform')&&$task->assigned_to===$r->user()->id,403);abort_unless(in_array($task->status,['scheduled','in_progress'],true),422,'This count is closed.');}
}
