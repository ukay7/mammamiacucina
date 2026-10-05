<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class EmailTemplateController extends Controller {
 public function index(){return view('admin.email-templates.index',['templates'=>EmailTemplate::all()->keyBy('key'),'definitions'=>config('email_templates')]);}
 public function edit(string $key){abort_unless(config('email_templates.'.$key),404);return view('admin.email-templates.edit',['template'=>EmailTemplate::findOrFail($key),'definition'=>config('email_templates.'.$key)]);}
 private function validateContent(Request $r,string $key):array {
  $definition=config('email_templates.'.$key);abort_unless($definition,404);
  $d=$r->validate(['subject'=>['required','string','max:255','not_regex:/[\r\n]/'],'body'=>'required|string|max:15000','revision'=>'required|integer|min:0']);
  preg_match_all('/\{\{(.*?)\}\}/s',$d['subject'].' '.$d['body'],$matches);
  foreach($matches[1] as $token)if(!in_array($token,$definition['tokens'],true))throw ValidationException::withMessages(['body'=>'Unknown placeholder: {{'.$token.'}}']);
  if($definition['required'] && !str_contains($d['body'],'{{'.$definition['required'].'}}'))throw ValidationException::withMessages(['body'=>'Keep {{'.$definition['required'].'}} in the body so the customer can complete the action.']);
  return $d;
 }
 public function update(Request $r,string $key){$d=$this->validateContent($r,$key);DB::transaction(function()use($key,$d){$t=EmailTemplate::whereKey($key)->lockForUpdate()->firstOrFail();if($t->revision!=$d['revision'])throw ValidationException::withMessages(['revision'=>'This template changed. Reload before saving.']);$t->update(['subject'=>$d['subject'],'body'=>$d['body'],'revision'=>$t->revision+1]);});return back()->with('status','Email template saved. Future emails will use this text.');}
}
