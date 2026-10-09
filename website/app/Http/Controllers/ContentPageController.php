<?php
namespace App\Http\Controllers;
use App\Models\{SitePolicy,CareerDepartment,CareerJob};
use Illuminate\Support\Facades\Storage;
class ContentPageController extends Controller {
 public function policies(){return view('pages.policies',['pageSettings'=>\App\Models\ContentPageSetting::forPage('policies'),'policies'=>SitePolicy::where('is_active',true)->orderBy('sort_order')->orderBy('title')->get()]);}
 public function careers(){return view('pages.careers',['pageSettings'=>\App\Models\ContentPageSetting::forPage('careers'),'departments'=>CareerDepartment::where('is_active',true)->with(['jobs'=>fn($q)=>$q->open()->orderBy('sort_order')->orderBy('title')])->orderBy('sort_order')->orderBy('title')->get()]);}
 public function job(int $id){return view('pages.career-job',['job'=>CareerJob::open()->with('department')->findOrFail($id)]);}
 public function image(CareerDepartment $department){abort_unless($department->is_active || (auth()->user()?->is_active && auth()->user()?->hasAdminPermission('settings.manage')),404);$disk=Storage::disk('local');abort_unless($department->image_path && $disk->exists($department->image_path),404);return response()->file($disk->path($department->image_path),['X-Content-Type-Options'=>'nosniff','Cache-Control'=>'private, no-store'])->setPrivate();}
}
