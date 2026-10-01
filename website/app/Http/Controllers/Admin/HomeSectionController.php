<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\HomeSection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Validation\ValidationException;
class HomeSectionController extends Controller {
 public function edit(){return view('admin.settings.home-sections',['sections'=>HomeSection::all()]);}
 public function image(string $section){$row=HomeSection::findOrFail($section);abort_unless($row->image_path && Storage::disk('local')->exists($row->image_path),404);return response()->file(Storage::disk('local')->path($row->image_path),['X-Content-Type-Options'=>'nosniff','Cache-Control'=>'no-cache']);}
 public function update(Request $request,string $section){
  abort_unless(in_array($section,['tradition','baking']),404);
  $rules=['revision'=>'required|integer|min:0','eyebrow'=>'required|string|max:100','heading'=>'required|string|max:200','description'=>'nullable|string|max:3000','image_alt'=>'required|string|max:255','image'=>'nullable|image|mimes:png,jpg,jpeg,webp|max:5120'];
  if($section==='baking')$rules+=['features'=>'required|array|size:3','features.*'=>'array:heading,description','features.*.heading'=>'required|string|max:150','features.*.description'=>'required|string|max:1000'];
  $data=$request->validate($rules);$path=null;$old=null;
  try {
   if($request->hasFile('image')){$path=$request->file('image')->store('home-sections','local');if(!$path)throw new \RuntimeException('Image upload failed.');}
   DB::transaction(function()use($section,$data,$path,&$old){
    $row=HomeSection::whereKey($section)->lockForUpdate()->firstOrFail();
    if($row->revision!==(int)$data['revision'])throw ValidationException::withMessages(['revision'=>'This section changed. Reload before saving.']);
    $content=$row->content;foreach(['eyebrow','heading','description','image_alt','features'] as $key)if(array_key_exists($key,$data))$content[$key]=$data[$key]??'';
    $row->content=$content;if($path){$old=$row->image_path;$row->image_path=$path;}$row->revision++;$row->save();
   });
  }catch(\Throwable $e){if($path)Storage::disk('local')->delete($path);throw $e;}
  if($old)Storage::disk('local')->delete($old);
  return redirect()->route('admin.home-sections.edit')->with('status','Homepage section saved.');
 }
}
