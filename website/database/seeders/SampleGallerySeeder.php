<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\{DB,Storage};
use App\Models\GalleryEvent;
class SampleGallerySeeder extends Seeder {
 public function run():void {
  $collections=[
   ['Italian Cake Collection','A sample gallery of our Italian cakes, using existing bakery artwork.',['loved-cassata.png','loved-limone.png','loved-cioccolato.png','loved-pistacchio.png']],
   ['Pastries & Cannoli Showcase','A sample collection celebrating Italian pastries and cannoli.',['category-pastries-hd.png','category-cannoli-hd.png','tradition-pastries.png','cannoli-hero.png']],
   ['Celebration Favourites','A sample selection of cakes and tarts for special occasions.',['arrival-sacher.png','arrival-saint-honore.png','arrival-frutti.png','arrival-caramello.png']],
  ];
  foreach($collections as $i=>[$title,$description,$files]){
   $paths=[];
   try { DB::transaction(function()use($title,$description,$files,$i,&$paths){
    $event=GalleryEvent::firstOrCreate(['title'=>$title],['description'=>$description,'event_date'=>null,'is_active'=>true,'sort_order'=>$i+1]);
    foreach($files as $file){
     $path='gallery/sample-'.$event->id.'/'.$file;
     if($event->photos()->where('path',$path)->exists())continue;
     if(!Storage::disk('local')->put($path,file_get_contents(public_path('assets/images/mmc/'.$file))))throw new \RuntimeException('Unable to store gallery image.');
     $paths[]=$path;$event->photos()->create(['path'=>$path]);
    }
    $this->command?->info($event->title.': '.$event->photos()->count().' photos');
   }); }catch(\Throwable $e){foreach($paths as $path)Storage::disk('local')->delete($path);throw $e;}
  }
 }
}
