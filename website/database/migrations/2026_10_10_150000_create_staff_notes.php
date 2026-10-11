<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::create('staff_notes', function(Blueprint $t){
  $t->id(); $t->foreignId('user_id')->constrained()->cascadeOnDelete();
  $t->string('title',120); $t->text('body')->nullable(); $t->string('color',20)->default('butter');
  $t->date('due_date')->nullable(); $t->boolean('pinned')->default(false); $t->timestamp('completed_at')->nullable(); $t->timestamps();
  $t->index(['user_id','completed_at']);
 }); }
 public function down(): void { Schema::dropIfExists('staff_notes'); }
};
