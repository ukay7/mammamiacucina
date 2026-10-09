<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void{Schema::create('content_page_settings',function(Blueprint $t){$t->id();$t->string('page')->unique();$t->string('title');$t->string('eyebrow')->nullable();$t->string('heading');$t->text('introduction')->nullable();$t->timestamps();});}
 public function down():void{Schema::dropIfExists('content_page_settings');}
};
