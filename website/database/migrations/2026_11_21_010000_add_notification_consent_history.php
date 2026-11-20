<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notification_subscriptions', function (Blueprint $t) {
            $t->id();
            $t->string('channel',30)->default('email');
            $t->string('address');
            $t->string('status',30)->default('unknown');
            $t->unsignedInteger('version')->default(0);
            $t->timestamp('unsubscribed_at')->nullable();
            $t->timestamp('consented_at')->nullable();
            $t->timestamps();
            $t->unique(['channel','address']);
            $t->index(['status','id']);
        });
        Schema::create('notification_consent_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('subscription_id')->constrained('notification_subscriptions')->restrictOnDelete();
            $t->string('action',30);
            $t->string('source',40);
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('actor_name')->nullable();
            $t->foreignId('delivery_id')->nullable()->constrained('notification_deliveries')->nullOnDelete();
            $t->uuid('request_key')->nullable()->unique();
            $t->unsignedInteger('version');
            $t->timestamp('consented_at')->nullable();
            $t->longText('evidence')->nullable();
            $t->longText('files')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['subscription_id','id']);
            $t->index(['action','created_at']);
        });
        Schema::table('notification_deliveries', function (Blueprint $t) {
            $t->unsignedInteger('consent_version')->default(0);
            $t->index(['address','channel','is_test'],'notification_delivery_address_idx');
        });
        // Register old recipients without inventing consent. Keep old suppression rows for rollback safety.
        foreach (['notification_deliveries','notification_suppressions'] as $table) {
            DB::table($table)->select(['id','address'])->where('channel','email')->orderBy('id')->chunkById(500,function($rows) {
                $now=now(); $records=[];
                foreach ($rows as $row) $records[strtolower(trim($row->address))]=['channel'=>'email','address'=>strtolower(trim($row->address)),'status'=>'unknown','version'=>0,'created_at'=>$now,'updated_at'=>$now];
                DB::table('notification_subscriptions')->insertOrIgnore(array_values($records));
            });
        }
        DB::table('notification_subscriptions')->orderBy('id')->chunkById(500,function($rows) {
            foreach ($rows as $row) {
                $suppression=DB::table('notification_suppressions')->where('channel','email')->whereRaw('LOWER(TRIM(address)) = ?',[$row->address])->where('reason','unsubscribed')->first();
                $event=DB::table('notification_events as e')->join('notification_deliveries as d','d.id','=','e.delivery_id')
                    ->where('d.channel','email')->whereRaw('LOWER(TRIM(d.address)) = ?',[$row->address])->where('e.type','unsubscribed')->orderByDesc('e.occurred_at')->select('e.*')->first();
                if (!$suppression && !$event) continue;
                $at=$event?->occurred_at ?? $suppression->created_at;
                DB::table('notification_subscriptions')->where('id',$row->id)->update(['status'=>'unsubscribed','version'=>1,'unsubscribed_at'=>$at]);
                DB::table('notification_consent_events')->insert(['subscription_id'=>$row->id,'action'=>'unsubscribed','source'=>'legacy',
                    'delivery_id'=>$event?->delivery_id,'version'=>1,'created_at'=>$at]);
            }
        });
    }
    public function down(): void
    {
        Schema::table('notification_deliveries', function (Blueprint $t) {
            $t->dropIndex('notification_delivery_address_idx');
            $t->dropColumn('consent_version');
        });
        Schema::dropIfExists('notification_consent_events');
        Schema::dropIfExists('notification_subscriptions');
    }
};
