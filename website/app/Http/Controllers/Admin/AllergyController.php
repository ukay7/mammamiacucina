<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Allergy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Storage,DB};
use Illuminate\Validation\Rule;
class AllergyController extends Controller {
 public function index(){return view('admin.allergies.index',['allergies'=>Allergy::withCount('products')->orderBy('name')->paginate(30)]);}
 public function create(){return view('admin.allergies.form',['allergy'=>new Allergy]);}
 public function edit(Allergy $allergy){return view('admin.allergies.form',compact('allergy'));}
 public function store(Request $r){return $this->save($r,new Allergy);}
 public function update(Request $r,Allergy $allergy){return $this->save($r,$allergy);}
 private function save(Request $r,Allergy $allergy){
  $d=$r->validate(['name'=>['required','string','max:120',Rule::unique('allergies')->ignore($allergy->id)],'icon'=>[$allergy->exists?'nullable':'required','file','image','mimes:png,jpg,jpeg,webp','max:2048']]);unset($d['icon']);$path=null;$old=null;
  try{if($r->hasFile('icon')){$path=$r->file('icon')->store('allergies','local');if(!$path)throw new \RuntimeException('Unable to store icon.');$d['icon_path']=$path;$d['asset_path']=null;}
   DB::transaction(function()use($allergy,$d,&$old){if($allergy->exists){$allergy->setRawAttributes(Allergy::lockForUpdate()->findOrFail($allergy->id)->getAttributes(),true);$old=$allergy->icon_path;}$allergy->fill($d)->save();});
  }catch(\Throwable $e){if($path)Storage::disk('local')->delete($path);throw $e;}
  if($path && $old)Storage::disk('local')->delete($old);return redirect()->route('admin.allergies.index')->with('status','Allergy saved.');
 }
 public function destroy(Allergy $allergy){$path=DB::transaction(function()use($allergy){$allergy=Allergy::lockForUpdate()->findOrFail($allergy->id);if($allergy->products()->exists())throw \Illuminate\Validation\ValidationException::withMessages(['allergy'=>'This allergy is assigned to products. Remove those assignments before deleting it.']);$path=$allergy->icon_path;$allergy->delete();return $path;});if($path)Storage::disk('local')->delete($path);return back()->with('status','Allergy deleted.');}
 public function icon(Allergy $allergy){abort_unless($allergy->icon_path && Storage::disk('local')->exists($allergy->icon_path),404);return response()->file(Storage::disk('local')->path($allergy->icon_path),['X-Content-Type-Options'=>'nosniff','Cache-Control'=>'no-cache']);}
}
