<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema,Storage};
class MostLovedSeeder extends Seeder {
 public function run(): void {
  $category=DB::table('categories')->where('name','Our Most Loved Treats')->first();
  $id=$category?->id ?? DB::table('categories')->insertGetId(['name'=>'Our Most Loved Treats','slug'=>'our-most-loved-treats','is_active'=>true,'sort_order'=>0,'created_at'=>now(),'updated_at'=>now()]);
  DB::table('categories')->where('id',$id)->update(['homepage_key'=>'most_loved','is_protected'=>true,'show_to_customer'=>false]);
  $matches=['Sicilian Cassata Cake','Lemon Caprese Cake','Chocolate Caprese Cake','Torta Ricotta Panna','Pistachio Crunch Cake','Profiterol Cioccolato'];
  $items=(require config_path('most-loved.php'))['products'];
  foreach($items as $i=>$item){
   $p=DB::table('products')->whereIn('premium_marketing_name',[$item['name'],$matches[$i]])->orderBy('id')->first();
   $pid=$p?->id ?? DB::table('products')->insertGetId(['category_id'=>$id,'premium_marketing_name'=>$item['name'],'slug'=>$item['slug'],'total_selling_price_cad'=>$item['price_cents']/100,'is_active'=>true,'created_at'=>now(),'updated_at'=>now()]);
   if(!DB::table('inventories')->where('product_id',$pid)->exists()) DB::table('inventories')->insert(['product_id'=>$pid,'quantity_on_hand'=>null,'created_at'=>now(),'updated_at'=>now()]);
   DB::table('category_product')->insertOrIgnore(['category_id'=>$id,'product_id'=>$pid]);
   if(!DB::table('product_media')->where('product_id',$pid)->where('kind','image')->exists()){
    $file=basename($item['image']);$path='products/'.$pid.'/'.$file;
    if(!Storage::disk('local')->put($path,file_get_contents(public_path($item['image']))))throw new \RuntimeException('Unable to save featured image.');
    DB::table('product_media')->insert(['product_id'=>$pid,'path'=>$path,'original_name'=>$file,'mime_type'=>'image/png','kind'=>'image','sort_order'=>0,'created_at'=>now(),'updated_at'=>now()]);
   }
  }
 }
}
