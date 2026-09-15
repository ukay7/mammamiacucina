<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
class HomeCategoriesSeeder extends Seeder {
 public function run(): void {
  foreach ([['Traditional Cakes','cakes','category-cakes-hd.png'],['Operetta Pastries','pastries','category-pastries-hd.png'],['Cannoli & Cornetti','cannoli','category-cannoli-hd.png']] as $index=>$item) {
   [$name,$slug,$file]=$item;
   $category=DB::table('categories')->where('name',$name)->first();
   if (!$category) {
    if(DB::table('categories')->where('slug',$slug)->exists()) $slug='home-'.$slug;
    $id=DB::table('categories')->insertGetId(['name'=>$name,'slug'=>$slug,'is_active'=>true,'show_to_customer'=>true,'is_protected'=>false,'sort_order'=>$index+1,'created_at'=>now(),'updated_at'=>now()]);
    $category=DB::table('categories')->find($id);
   }
   if (!$category->image_path) {
    $source=public_path('assets/images/mmc/'.$file);
    $path='categories/home-'.$category->id.'-'.$file;
    if (!Storage::disk('local')->put($path,file_get_contents($source))) throw new \RuntimeException('Unable to save category image.');
    DB::table('categories')->where('id',$category->id)->update(['image_path'=>$path]);
   }
  }
 }
}
