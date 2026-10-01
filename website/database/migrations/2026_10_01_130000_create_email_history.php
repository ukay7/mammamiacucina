<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::table('users',fn(Blueprint $t)=>$t->timestamp('verification_last_sent_at')->nullable());
  Schema::create('email_history',function(Blueprint $t){
   $t->id();$t->string('recipient')->index();$t->string('type',50);$t->string('subject');
   $t->string('status',30)->index();$t->string('mailer')->nullable();$t->text('message_id')->nullable();
   $t->timestamp('sent_at')->nullable();$t->timestamps();
  });
 }
 public function down():void {Schema::dropIfExists('email_history');Schema::table('users',fn(Blueprint $t)=>$t->dropColumn('verification_last_sent_at'));}
};
