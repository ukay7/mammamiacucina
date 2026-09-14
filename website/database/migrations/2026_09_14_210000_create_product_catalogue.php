<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $t) {
            $t->id();
            $t->string('name', 120)->unique();
            $t->string('slug')->unique();
            $t->text('description')->nullable();
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });
        DB::table('categories')->insert(['name' => 'General', 'slug' => 'general', 'is_active' => true, 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now()]);
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->foreignId('category_id')->constrained()->restrictOnDelete();
            $t->string('slug')->unique();
            foreach (['supplier', 'product_code', 'qr_code', 'premium_marketing_name', 'italian_subtitle', 'status', 'uom', 'size_diameter_cm'] as $field) {
                $t->string($field)->nullable();
            }
            $t->unique('qr_code');
            $t->index('product_code');
            foreach (['original_description', 'product_notes'] as $field) {
                $t->text($field)->nullable();
            }
            foreach (['invoice_quantity', 'cartons', 'pieces_per_pack', 'unit_weight_g', 'pack_weight_kg', 'supplier_unit_price_eur', 'supplier_discount', 'supplier_unit_price_eur_2', 'supplier_line_total_eur', 'rate_exchange_cad', 'shipping_cost_cad', 'surcharge_increase_cad', 'total_selling_price_cad'] as $field) {
                $t->decimal($field, 20, 8)->nullable();
            }
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('inventories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->unique()->constrained()->restrictOnDelete();
            $t->decimal('quantity_on_hand', 15, 3)->nullable();
            $t->decimal('low_stock_threshold', 15, 3)->default(0);
            $t->timestamps();
        });
        Schema::create('inventory_movements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->restrictOnDelete();
            $t->decimal('quantity_change', 15, 3);
            $t->decimal('quantity_before', 15, 3)->nullable();
            $t->decimal('quantity_after', 15, 3);
            $t->text('reason');
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at')->useCurrent();
        });
        Schema::create('product_imports', function (Blueprint $t) {
            $t->id();
            $t->string('original_filename');
            $t->string('stored_path')->nullable();
            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('category_id')->constrained()->restrictOnDelete();
            $t->string('status')->default('preview');
            foreach (['total_rows', 'created_count', 'updated_count', 'failed_count', 'skipped_count'] as $f) {
                $t->unsignedInteger($f)->default(0);
            }$t->timestamps();
        });
        Schema::create('product_import_rows', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_import_id')->constrained()->cascadeOnDelete();
            $t->string('source_sheet');
            $t->unsignedInteger('row_number');
            $t->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $t->json('original_data');
            $t->json('normalized_data')->nullable();
            $t->string('status');
            $t->json('errors')->nullable();
        });
        $role = DB::table('roles')->where('name', 'Administrator')->first();
        if ($role) {
            $permissions = json_decode($role->permissions, true) ?? [];
            DB::table('roles')->where('id', $role->id)->update(['permissions' => json_encode(array_values(array_unique([...$permissions, 'categories.view', 'categories.manage', 'products.view', 'products.manage', 'imports.view', 'imports.manage', 'inventory.view', 'inventory.manage'])))]);
        }
    }

    public function down(): void
    {
        foreach (['product_import_rows', 'product_imports', 'inventory_movements', 'inventories', 'products', 'categories'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
