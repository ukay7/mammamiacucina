<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\{SitePolicy,CareerDepartment,CareerJob};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
class ContentController extends Controller {
 private function model(string $kind):string {return match($kind){'policies'=>SitePolicy::class,'departments'=>CareerDepartment::class,'jobs'=>CareerJob::class,default=>abort(404)};}
 public function index(string $kind){$class=$this->model($kind);$records=$class::orderBy('sort_order')->orderBy('title')->paginate(20);$pageSettings=\App\Models\ContentPageSetting::forPage($kind==='policies'?'policies':'careers');return view('admin.content.index',compact('kind','records','pageSettings'));}
 public function page(Request $r,string $kind){abort_unless(in_array($kind,['policies','departments']),404);$data=$r->validate(['title'=>'required|string|max:255','eyebrow'=>'nullable|string|max:255','heading'=>'required|string|max:255','introduction'=>'nullable|string|max:5000']);$settings=\App\Models\ContentPageSetting::forPage($kind==='policies'?'policies':'careers');$settings->fill($data)->save();return back()->with('status','Page introduction saved.');}
 public function create(string $kind){$class=$this->model($kind);return $this->form($kind,new $class);}
 public function edit(string $kind,int $id){$class=$this->model($kind);return $this->form($kind,$class::findOrFail($id));}
 private function form($kind,$record){$departments=CareerDepartment::orderBy('title')->get();return view('admin.content.form',compact('kind','record','departments'));}
 public function store(Request $r,string $kind){$class=$this->model($kind);return $this->save($r,$kind,new $class);}
 public function update(Request $r,string $kind,int $id){$class=$this->model($kind);return $this->save($r,$kind,$class::findOrFail($id));}
 private function save(Request $r,string $kind,$record){
  $rules=['title'=>'required|string|max:255','is_active'=>'required|boolean','sort_order'=>'required|integer|min:0|max:99999'];
  $rules+=match($kind){
   'policies'=>['summary'=>'nullable|string|max:500','body'=>'required|string|max:100000','effective_date'=>'nullable|date_format:Y-m-d'],
   'departments'=>['description'=>'nullable|string|max:5000','image'=>'nullable|image|mimes:jpg,jpeg,png,webp|max:5120','image_alt'=>'nullable|string|max:255','remove_image'=>'sometimes|boolean'],
   'jobs'=>['department_id'=>'required|exists:career_departments,id','location'=>'required|string|max:255','employment_type'=>'required|in:Full-time,Part-time,Contract,Temporary,Internship','salary'=>'nullable|string|max:255','description'=>'required|string|max:20000','responsibilities'=>'nullable|string|max:15000','skills'=>'required|string|max:15000','benefits'=>'nullable|string|max:10000','application_email'=>'required|email|max:255','application_instructions'=>'nullable|string|max:5000','closing_date'=>'nullable|date_format:Y-m-d']
  };
  $data=$r->validate($rules);unset($data['image'],$data['remove_image']);$old=$record->image_path;$new=null;
  if($kind==='departments'){
   if($r->boolean('remove_image'))$data['image_path']=null;
   if($r->hasFile('image')){ $new=$r->file('image')->store('career-departments','local');if(!$new)throw ValidationException::withMessages(['image'=>'Image could not be saved. Please try again.']);$data['image_path']=$new; }
  }
  try{$record->fill($data)->save();}catch(\Throwable $e){if($new)Storage::disk('local')->delete($new);throw $e;}
  if($old && $old!==$record->image_path)Storage::disk('local')->delete($old);
  return redirect()->route('admin.content.index',$kind)->with('status','Saved successfully. Only published content appears on the website.');
 }
 public function destroy(string $kind,int $id){$class=$this->model($kind);$record=$class::findOrFail($id);if($kind==='departments' && $record->jobs()->exists())throw ValidationException::withMessages(['department'=>'Move or delete this department’s job postings first.']);$path=$record->image_path;$record->delete();if($path)Storage::disk('local')->delete($path);return back()->with('status','Deleted successfully.');}
}
