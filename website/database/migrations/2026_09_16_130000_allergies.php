<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema,DB};
return new class extends Migration {
 public function up():void {
  Schema::create('allergies',function(Blueprint $t){$t->id();$t->string('name',120)->unique();$t->string('icon_path')->nullable();$t->string('asset_path')->nullable();$t->timestamps();});
  Schema::create('allergy_product',function(Blueprint $t){$t->foreignId('allergy_id')->constrained()->restrictOnDelete();$t->foreignId('product_id')->constrained()->cascadeOnDelete();$t->primary(['allergy_id','product_id']);});
  foreach(['CITRUS.png','COCONUT.png','contains Berries.png','contains Fruit.png','CONTAINS MILK.png','CONTAINS WHEAT.png','Egg.png','Hazelnuts.png','PEANUTS.png','PISTACHIOS.png','Vegetarian.png'] as $file){DB::table('allergies')->insert(['name'=>ucwords(strtolower(pathinfo($file,PATHINFO_FILENAME))),'asset_path'=>'media/icons/'.$file,'created_at'=>now(),'updated_at'=>now()]);}
 }
 public function down():void {Schema::dropIfExists('allergy_product');Schema::dropIfExists('allergies');}
};
