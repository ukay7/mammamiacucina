<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pricing_lists', function (Blueprint $table) {
            $table->string('number',32)->nullable()->unique();
        });
        // Reserve numbers independently of saving a confirmed list. Auto-increment
        // prevents two staff members from receiving the same preview number.
        Schema::create('pricing_list_numbers', function (Blueprint $table) {
            $table->id();
            $table->uuid('draft_key')->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('pricing_list_id')->nullable()->unique()->constrained('pricing_lists');
            $table->uuid('confirmed_token')->nullable();
            $table->timestamps();
        });
        // Preserve every existing public number, including deleted lists.
        DB::table('pricing_lists')->select(['id','created_by','created_at'])->orderBy('id')->chunkById(100,function($lists){
            foreach ($lists as $list) {
                DB::table('pricing_list_numbers')->insert(['id'=>$list->id,'draft_key'=>(string)Str::uuid(),
                    'created_by'=>$list->created_by,'pricing_list_id'=>$list->id,
                    'created_at'=>$list->created_at,'updated_at'=>now()]);
                DB::table('pricing_lists')->where('id',$list->id)->update(['number'=>'PL-'.str_pad((string)$list->id,5,'0',STR_PAD_LEFT)]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_list_numbers');
        Schema::table('pricing_lists', function (Blueprint $table) {
            $table->dropUnique(['number']);
            $table->dropColumn('number');
        });
    }
};
