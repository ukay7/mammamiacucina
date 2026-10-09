<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\ThemeSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class ThemeSettingController extends Controller {
 public function edit(){return view('admin.settings.theme',['theme'=>ThemeSetting::findOrFail(1)]);}
 public function update(Request $r){
  $rules=['revision'=>'required|integer|min:0','action'=>'required|in:save,reset'];
  if($r->input('action')==='save')foreach(['website','admin'] as $scope){
   $rules[$scope]='required|array:'.implode(',',array_keys(config('theme_colors.colors')));
   foreach(config('theme_colors.colors') as $key=>$value)$rules[$scope.'.'.$key]=['required','regex:/^#[0-9a-fA-F]{6}$/'];
  }
  $d=$r->validate($rules);
  DB::transaction(function()use($d){$t=ThemeSetting::lockForUpdate()->findOrFail(1);
   if($t->revision!==(int)$d['revision'])throw ValidationException::withMessages(['theme'=>'Theme changed. Reload before saving.']);
   $t->update(['website'=>$d['action']==='reset'?null:$d['website'],'admin'=>$d['action']==='reset'?null:$d['admin'],'revision'=>$t->revision+1]);
  });
  return back()->with('status',$d['action']==='reset'?'Original theme restored.':'Theme saved for the website and admin portal.');
 }
}
