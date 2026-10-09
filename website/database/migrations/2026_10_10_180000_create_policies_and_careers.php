<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('site_policies',function(Blueprint $t){$t->id();$t->string('title');$t->string('summary',500)->nullable();$t->longText('body');$t->date('effective_date')->nullable();$t->boolean('is_active')->default(false);$t->unsignedInteger('sort_order')->default(0);$t->timestamps();});
  Schema::create('career_departments',function(Blueprint $t){$t->id();$t->string('title');$t->text('description')->nullable();$t->string('image_path')->nullable();$t->string('image_alt')->nullable();$t->boolean('is_active')->default(false);$t->unsignedInteger('sort_order')->default(0);$t->timestamps();});
  Schema::create('career_jobs',function(Blueprint $t){$t->id();$t->foreignId('department_id')->constrained('career_departments')->restrictOnDelete();$t->string('title');$t->string('location');$t->string('employment_type');$t->string('salary')->nullable();$t->longText('description');$t->text('responsibilities')->nullable();$t->text('skills');$t->text('benefits')->nullable();$t->string('application_email');$t->text('application_instructions')->nullable();$t->date('closing_date')->nullable();$t->boolean('is_active')->default(false);$t->unsignedInteger('sort_order')->default(0);$t->timestamps();});
 }
 public function down():void{Schema::dropIfExists('career_jobs');Schema::dropIfExists('career_departments');Schema::dropIfExists('site_policies');}
};
