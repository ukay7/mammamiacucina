<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_application_templates', function (Blueprint $table) {
            $table->id();
            $table->string('title', 100);
            $table->text('application_terms');
            $table->text('guarantee_terms');
            $table->text('consent_statement');
            $table->unsignedInteger('revision')->default(0);
            $table->string('legal_approval_reference', 255)->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();
        });

        Schema::create('credit_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('template_id')->constrained('credit_application_templates')->restrictOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('invitation_email_id')->nullable()->constrained('email_history')->nullOnDelete();
            $table->string('status', 24)->default('awaiting_signature');
            $table->longText('draft_details')->nullable();
            $table->char('review_token', 64)->nullable();
            $table->timestamp('prepared_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->longText('signed_snapshot')->nullable();
            $table->longText('signed_pdf')->nullable();
            $table->char('pdf_sha256', 64)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reviewer_name')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->index(['customer_id', 'status']);
            $table->index(['status', 'id']);
        });

        $invitation = config('email_templates.credit_application_invitation');
        DB::table('email_templates')->insertOrIgnore([
            'key' => 'credit_application_invitation', 'subject' => $invitation['subject'], 'body' => $invitation['body'],
            'revision' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (DB::table('credit_applications')->whereNotNull('submitted_at')->exists()) {
            throw new RuntimeException('Signed credit applications must be preserved.');
        }
        Schema::dropIfExists('credit_applications');
        Schema::dropIfExists('credit_application_templates');
    }
};
