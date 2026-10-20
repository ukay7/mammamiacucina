<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stock_count_item_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_count_item_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity_change', 15, 3);
            $table->decimal('quantity_before', 15, 3);
            $table->decimal('quantity_after', 15, 3);
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->index(['stock_count_item_id', 'created_at']);
        });

        DB::table('stock_count_items as items')
            ->join('stock_count_tasks as tasks', 'tasks.id', '=', 'items.stock_count_task_id')
            ->whereNotNull('items.actual_quantity')
            ->orderBy('items.id')
            ->select(['items.id', 'items.actual_quantity', 'items.note', 'items.counted_at', 'items.updated_at', 'tasks.assigned_to'])
            ->chunk(250, function ($items) {
                DB::table('stock_count_item_logs')->insert($items->map(fn ($item) => [
                    'stock_count_item_id' => $item->id,
                    'quantity_change' => $item->actual_quantity,
                    'quantity_before' => 0,
                    'quantity_after' => $item->actual_quantity,
                    'note' => $item->note ?: 'Opening count migrated from saved progress.',
                    'created_by' => $item->assigned_to,
                    'created_at' => $item->counted_at ?: $item->updated_at,
                ])->all());
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_count_item_logs');
    }
};
