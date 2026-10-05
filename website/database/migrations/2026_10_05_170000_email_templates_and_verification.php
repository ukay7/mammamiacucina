<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema,DB};
return new class extends Migration {
 public function up():void {
  Schema::table('smtp_settings',function(Blueprint $t){$t->timestamp('tested_at')->nullable();$t->unsignedInteger('tested_revision')->nullable();});
  Schema::create('email_templates',function(Blueprint $t){$t->string('key')->primary();$t->string('subject');$t->text('body');$t->unsignedInteger('revision')->default(0);$t->timestamps();});
  foreach(config('email_templates') as $key=>$template)DB::table('email_templates')->insert(['key'=>$key,'subject'=>$template['subject'],'body'=>$template['body'],'created_at'=>now(),'updated_at'=>now()]);
  Schema::create('customer_email_verifications',function(Blueprint $t){$t->id();$t->foreignId('user_id')->constrained('users')->cascadeOnDelete();$t->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();$t->string('reason',500);$t->timestamp('created_at');});
 }
 public function down():void {Schema::dropIfExists('customer_email_verifications');Schema::dropIfExists('email_templates');Schema::table('smtp_settings',fn(Blueprint $t)=>$t->dropColumn(['tested_at','tested_revision']));}
};
