<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notification_campaigns', function (Blueprint $t) {
            $t->id();
            $t->string('title', 150);
            $t->string('channel', 30)->default('email');
            $t->string('subject');
            $t->longText('body');
            $t->string('status', 30)->default('draft')->index();
            $t->string('audience_mode', 20)->default('selected');
            $t->string('customer_type', 20)->default('all');
            $t->longText('audience');
            $t->boolean('include_alternate')->default(true);
            $t->boolean('track_opens')->default(true);
            $t->boolean('track_clicks')->default(true);
            $t->unsignedInteger('revision')->default(0);
            $t->unsignedInteger('tested_revision')->nullable();
            $t->unsignedInteger('tested_settings_revision')->nullable();
            $t->timestamp('tested_at')->nullable();
            $t->longText('review')->nullable();
            $t->longText('snapshot')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('queued_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('scheduled_at')->nullable();
            $t->timestamp('queued_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('notification_deliveries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('campaign_id')->constrained('notification_campaigns')->cascadeOnDelete();
            $t->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $t->string('channel', 30)->default('email');
            $t->string('address');
            $t->string('recipient_name')->nullable();
            $t->string('source', 30);
            $t->boolean('is_test')->default(false);
            $t->unsignedInteger('campaign_revision');
            $t->unsignedInteger('settings_revision')->nullable();
            $t->string('dedupe_key', 64)->nullable();
            $t->unique(['campaign_id', 'dedupe_key']);
            $t->string('token', 64)->unique();
            $t->uuid('idempotency_key')->unique();
            $t->string('status', 30)->default('queued');
            $t->string('outcome', 30)->nullable();
            $t->string('provider', 30)->nullable();
            $t->string('provider_message_id')->nullable()->index();
            $t->string('credential_hash', 64)->nullable();
            $t->longText('payload')->nullable();
            $t->longText('test_snapshot')->nullable();
            $t->unsignedInteger('attempts')->default(0);
            $t->timestamp('available_at')->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('first_attempt_at')->nullable();
            $t->timestamp('accepted_at')->nullable();
            $t->timestamp('delivered_at')->nullable();
            $t->timestamp('opened_at')->nullable();
            $t->timestamp('last_opened_at')->nullable();
            $t->unsignedInteger('open_count')->default(0);
            $t->timestamp('clicked_at')->nullable();
            $t->unsignedInteger('click_count')->default(0);
            $t->string('note', 500)->nullable();
            $t->timestamps();
            $t->index(['status', 'available_at']);
            $t->index(['campaign_id', 'is_test', 'status']);
        });
        Schema::create('notification_links', function (Blueprint $t) {
            $t->id();
            $t->foreignId('campaign_id')->constrained('notification_campaigns')->cascadeOnDelete();
            $t->string('token', 40)->unique();
            $t->string('url_hash', 64);
            $t->text('url');
            $t->unique(['campaign_id', 'url_hash']);
        });
        Schema::create('notification_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('delivery_id')->nullable()->constrained('notification_deliveries')->cascadeOnDelete();
            $t->string('event_key', 191)->nullable()->unique();
            $t->string('provider_message_id')->nullable()->index();
            $t->string('type', 50);
            $t->string('source', 30);
            $t->string('description', 500)->nullable();
            $t->timestamp('occurred_at');
            $t->timestamp('processed_at')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });
        Schema::create('notification_suppressions', function (Blueprint $t) {
            $t->id();
            $t->string('channel', 30)->default('email');
            $t->string('address');
            $t->string('reason', 30);
            $t->timestamps();
            $t->unique(['channel', 'address']);
        });
        Schema::create('notification_settings', function (Blueprint $t) {
            $t->id();
            $t->text('webhook_secret')->nullable();
            $t->unsignedInteger('per_run')->default(20);
            $t->timestamp('worker_ran_at')->nullable();
            $t->timestamp('webhook_received_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['notification_settings', 'notification_suppressions', 'notification_events', 'notification_links', 'notification_deliveries', 'notification_campaigns'] as $table) Schema::dropIfExists($table);
    }
};
