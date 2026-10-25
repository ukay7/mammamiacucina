<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->softDeletes();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('deletion_reason')->nullable();
            $table->boolean('deletion_restock')->nullable();
        });

        // Existing archives become read-only deleted records. Their historical
        // inventory movements are preserved; this migration never returns stock.
        DB::table('orders')->whereNotNull('archived_at')->update([
            'deleted_at' => DB::raw('archived_at'), 'deleted_by' => DB::raw('archived_by'),
            'deletion_reason' => 'Previously archived; moved to Deleted Orders.', 'deletion_restock' => false,
        ]);
        Schema::table('email_history', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
        });
        DB::table('email_history')->whereNotNull('event_key')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                $orderId = null;
                if (preg_match('/^order-(?:created|status|paid):(\d+)(?::|$)/', $row->event_key, $match)) {
                    $orderId = $match[1];
                } elseif (preg_match('/^(settlement|invoice-email):(\d+)$/', $row->event_key, $match)) {
                    $orderId = DB::table($match[1] === 'settlement' ? 'order_settlements' : 'customer_invoices')->where('id', $match[2])->value('order_id');
                }
                if ($orderId && DB::table('orders')->where('id', $orderId)->exists()) {
                    DB::table('email_history')->where('id', $row->id)->update(['order_id' => $orderId]);
                }
            }
        });
        DB::table('email_history')->whereIn('order_id', DB::table('orders')->whereNotNull('deleted_at')->select('id'))
            ->whereIn('status', ['queued', 'retry'])->update(['status' => 'cancelled', 'delivery_note' => 'Order deleted; email delivery cancelled.']);
    }

    public function down(): void
    {
        Schema::table('email_history', fn (Blueprint $table) => $table->dropConstrainedForeignId('order_id'));
        DB::table('orders')->whereNotNull('deleted_at')->update([
            'archived_at' => DB::raw('deleted_at'), 'archived_by' => DB::raw('deleted_by'),
        ]);
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deleted_by');
            $table->dropSoftDeletes();
            $table->dropColumn(['deletion_reason', 'deletion_restock']);
        });
    }
};
