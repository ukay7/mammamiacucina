<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('general_settings', function (Blueprint $table) {
            $table->string('document_company_name', 160)->nullable();
            $table->text('mailing_address')->nullable();
            $table->text('document_warehouse_address')->nullable();
            $table->string('gst_hst_number', 80)->nullable();
        });

        // Approved document details only. Keep existing contact details, pickup instructions,
        // charges, revision and all financial records intact.
        DB::table('general_settings')->where('id', 1)->update([
            'document_company_name' => 'Mamma Mia Cucina INC.',
            'mailing_address' => '1 Sir Bodwin Place, Markham, ON L3P 2X7',
            'document_warehouse_address' => '471 Jevlan Dr, Woodbridge, ON L4L 8E3',
            'gst_hst_number' => '737385034RT0001',
        ]);
    }

    public function down(): void
    {
        Schema::table('general_settings', function (Blueprint $table) {
            $table->dropColumn(['document_company_name', 'mailing_address', 'document_warehouse_address', 'gst_hst_number']);
        });
    }
};
