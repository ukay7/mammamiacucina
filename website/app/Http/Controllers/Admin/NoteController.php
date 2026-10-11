<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\StaffNote;
use Illuminate\Http\Request;
class NoteController extends Controller {
 public function index(Request $r) {
  $filter=in_array($r->query('filter'),['all','done','overdue'])?$r->query('filter'):'open';
  $base=StaffNote::where('user_id',$r->user()->id);
  $counts=['open'=>(clone $base)->whereNull('completed_at')->count(),'done'=>(clone $base)->whereNotNull('completed_at')->count(),'overdue'=>(clone $base)->whereNull('completed_at')->where('due_date','<',today())->count()];
  $notes=(clone $base)->when($filter==='open',fn($q)=>$q->whereNull('completed_at'))->when($filter==='done',fn($q)=>$q->whereNotNull('completed_at'))->when($filter==='overdue',fn($q)=>$q->whereNull('completed_at')->where('due_date','<',today()))->orderByDesc('pinned')->latest('updated_at')->orderByDesc('id')->paginate(12)->withQueryString();
  return view('admin.notes',compact('notes','counts','filter'));
 }
 private function data(Request $r): array { return $r->validate(['title'=>'required|string|max:120','body'=>'nullable|string|max:4000','color'=>'required|in:butter,sage,rose,sky','due_date'=>'nullable|date_format:Y-m-d','pinned'=>'required|boolean']); }
 private function own(Request $r,StaffNote $note): void { abort_unless($note->user_id===$r->user()->id,404); }
 public function store(Request $r) { $note=new StaffNote($this->data($r));$note->user_id=$r->user()->id;$note->save();return redirect()->route('admin.notes.index')->with('status','Note added to your board.'); }
 public function update(Request $r,StaffNote $note) { $this->own($r,$note);$note->update($this->data($r));return back()->with('status','Note updated.'); }
 public function complete(Request $r,StaffNote $note) { $this->own($r,$note);$data=$r->validate(['completed'=>'required|boolean']);$note->update(['completed_at'=>$data['completed']?now():null]);return back()->with('status',$data['completed']?'Note completed.':'Note reopened.'); }
 public function destroy(Request $r,StaffNote $note) { $this->own($r,$note);$note->delete();return back()->with('status','Note deleted.'); }
}
