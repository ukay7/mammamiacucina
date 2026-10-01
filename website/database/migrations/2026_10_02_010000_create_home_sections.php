<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema,DB};
return new class extends Migration {
 public function up():void {
  Schema::create('home_sections',function(Blueprint $t){$t->string('key')->primary();$t->json('content');$t->string('image_path')->nullable();$t->unsignedInteger('revision')->default(0);$t->timestamps();});
  foreach(config('home_sections') as $key=>$content)DB::table('home_sections')->insert(['key'=>$key,'content'=>json_encode($content),'created_at'=>now(),'updated_at'=>now()]);
 }
 public function down():void {Schema::dropIfExists('home_sections');}
};
