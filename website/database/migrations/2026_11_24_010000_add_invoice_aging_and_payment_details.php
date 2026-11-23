<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customers', fn (Blueprint $table) => $table->unsignedSmallInteger('invoice_term_days')->nullable());
        Schema::table('customer_invoices', fn (Blueprint $table) => $table->date('due_date')->nullable());
        Schema::table('general_settings', function (Blueprint $table) {
            $table->text('invoice_eft_transit')->nullable();
            $table->text('invoice_eft_institution')->nullable();
            $table->text('invoice_eft_account')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('general_settings', fn (Blueprint $table) => $table->dropColumn(['invoice_eft_transit', 'invoice_eft_institution', 'invoice_eft_account']));
        Schema::table('customer_invoices', fn (Blueprint $table) => $table->dropColumn('due_date'));
        Schema::table('customers', fn (Blueprint $table) => $table->dropColumn('invoice_term_days'));
    }
};
