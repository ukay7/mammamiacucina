<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::create('gallery_events',function(Blueprint $t){$t->id();$t->string('title',160);$t->text('description')->nullable();$t->date('event_date')->nullable();$t->boolean('is_active')->default(true);$t->unsignedInteger('sort_order')->default(0);$t->timestamps();});
  Schema::create('gallery_photos',function(Blueprint $t){$t->id();$t->foreignId('gallery_event_id')->constrained()->cascadeOnDelete();$t->string('path');$t->timestamps();});
  Schema::create('contact_enquiries',function(Blueprint $t){$t->id();$t->string('name',120);$t->string('email');$t->string('phone',60)->nullable();$t->text('message');$t->string('status',20)->default('new');$t->timestamps();});
 }
 public function down():void {Schema::dropIfExists('contact_enquiries');Schema::dropIfExists('gallery_photos');Schema::dropIfExists('gallery_events');}
};
