<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;

class ClientDemoReset
{
    public function run(): void
    {
        DB::transaction(function () {
            $users = DB::table('users')->whereIn('account_type', ['individual', 'business'])->pluck('id');
            $emails = DB::table('users')->whereIn('id', $users)->pluck('email');
            // Keep gateway event IDs: they protect against replaying old payment webhooks.
            foreach (['payment_refunds', 'payments', 'order_settlements', 'order_events', 'order_items', 'orders', 'quotations'] as $table) {
                DB::table($table)->delete();
            }
            DB::table('customer_email_verifications')->whereIn('user_id', $users)->delete();
            DB::table('password_reset_tokens')->whereIn('email', $emails)->delete();
            DB::table('email_history')->whereIn('recipient', $emails)->delete();
            DB::table('customers')->delete();
            DB::table('users')->whereIn('id', $users)->delete();
            DB::table('sessions')->whereIn('user_id', $users)->delete();
            // DELETE retains sequence values. Products, media and inventory stay intact.
        });
    }
}
