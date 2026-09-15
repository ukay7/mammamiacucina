<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema,DB};
return new class extends Migration {
 public function up(): void {
  Schema::table('general_settings',function(Blueprint $t){$t->string('logo_path')->nullable();$t->string('email')->nullable();$t->string('phone',60)->nullable();$t->string('youtube_video_id',11)->nullable();$t->json('opening_hours')->nullable();});
  $hours=[];foreach(['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $i=>$day)$hours[$day]=['open'=>$i<5?'08:00':'10:00','close'=>$i<5?'20:30':'16:30','closed'=>false];
  DB::table('general_settings')->where('id',1)->update(['youtube_video_id'=>config('homepage.youtube_video_id'),'opening_hours'=>json_encode($hours)]);
 }
 public function down(): void {Schema::table('general_settings',function(Blueprint $t){$t->dropColumn(['logo_path','email','phone','youtube_video_id','opening_hours']);});}
};
