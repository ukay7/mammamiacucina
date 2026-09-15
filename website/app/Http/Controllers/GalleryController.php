<?php
namespace App\Http\Controllers;
use App\Models\{GalleryEvent,GalleryPhoto};
use Illuminate\Support\Facades\Storage;
class GalleryController extends Controller {
 public function index(){return view('pages.gallery',['events'=>GalleryEvent::where('is_active',true)->whereHas('photos')->withCount('photos')->with(['photos'=>fn($q)=>$q->limit(1)])->orderBy('sort_order')->orderByDesc('event_date')->orderByDesc('id')->paginate(12)]);}
 public function show(GalleryEvent $event){abort_unless($event->is_active,404);return view('pages.gallery-event',['event'=>$event,'photos'=>$event->photos()->paginate(24)]);}
 public function image(GalleryPhoto $photo){abort_unless($photo->event->is_active || auth()->user()?->hasAdminPermission('gallery.manage'),404);abort_unless(Storage::disk('local')->exists($photo->path),404);return response()->file(Storage::disk('local')->path($photo->path),['X-Content-Type-Options'=>'nosniff','Cache-Control'=>'private, no-cache']);}
}
