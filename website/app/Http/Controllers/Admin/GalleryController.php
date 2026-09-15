<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\{GalleryEvent,GalleryPhoto};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Storage};
class GalleryController extends Controller {
 public function index(){return view('admin.gallery.index',['events'=>GalleryEvent::withCount('photos')->orderBy('sort_order')->latest('id')->paginate(20)]);}
 public function create(){return view('admin.gallery.form',['event'=>new GalleryEvent(['is_active'=>true,'sort_order'=>0])]);}
 public function edit(GalleryEvent $event){return view('admin.gallery.form',['event'=>$event->load('photos')]);}
 public function store(Request $r){return $this->save($r,new GalleryEvent);}
 public function update(Request $r,GalleryEvent $event){return $this->save($r,$event);}
 private function save(Request $r,GalleryEvent $event){
  $data=$r->validate(['title'=>'required|string|max:160','description'=>'nullable|string|max:5000','event_date'=>'nullable|date_format:Y-m-d','is_active'=>'required|boolean','sort_order'=>'required|integer|min:0|max:100000','photos'=>'nullable|array|max:20','photos.*'=>'required|file|image|mimes:jpg,jpeg,png,webp|max:5120']);unset($data['photos']);$paths=[];
  try {
   foreach($r->file('photos',[]) as $photo){$path=$photo->store('gallery','local');if(!$path)throw new \RuntimeException('Unable to store photo.');$paths[]=$path;}
   DB::transaction(function()use($event,$data,$paths){$event->fill($data)->save();foreach($paths as $path)$event->photos()->create(['path'=>$path]);});
  }catch(\Throwable $e){foreach($paths as $path)Storage::disk('local')->delete($path);throw $e;}
  return redirect()->route('admin.gallery.edit',$event)->with('status','Event saved. Photos are ready in the gallery.');
 }
 public function destroy(GalleryEvent $event){$paths=$event->photos()->pluck('path');$event->delete();foreach($paths as $path)Storage::disk('local')->delete($path);return redirect()->route('admin.gallery.index')->with('status','Event deleted.');}
 public function removePhoto(GalleryEvent $event,GalleryPhoto $photo){abort_unless($photo->gallery_event_id===$event->id,404);$path=$photo->path;$photo->delete();Storage::disk('local')->delete($path);return back()->with('status','Photo removed.');}
}
