<?php

namespace App\Services;

use App\Models\{Customer, CustomerInvoice, CustomerReceiptEvent, CustomerReceiptLine, EmailHistory, Order, OrderSettlement, Quotation, User};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;

class CustomerDocuments
{
    public const GROUPS = ['orders' => 'Orders', 'invoices' => 'Invoices', 'receipts' => 'Receipts & adjustments',
        'uploads' => 'Uploaded files', 'quotations' => 'Quotations', 'email' => 'Saved email PDFs'];

    private const KINDS = ['order' => 'orders', 'invoice' => 'invoices', 'initial-payment' => 'receipts',
        'settlement' => 'receipts', 'account-receipt' => 'receipts', 'refund' => 'receipts', 'credit-note' => 'receipts', 'transfer-proof' => 'uploads',
        'settlement-proof' => 'uploads', 'account-proof' => 'uploads', 'quotation' => 'quotations', 'email-copy' => 'email'];

    private function allowed(User $viewer, string $kind): bool
    {
        if ($viewer->isCustomer() || $viewer->isSuper()) return true;
        return match ($kind) {
            // Keep the existing staff boundaries for account receipts and archived mail.
            'account-receipt', 'account-proof', 'refund', 'credit-note' => false,
            'quotation' => $viewer->hasAdminPermission('quotations.view') || $viewer->hasAdminPermission('quotations.manage'),
            'email-copy' => $viewer->hasAdminPermission('settings.manage') && $viewer->hasAdminPermission('orders.view'),
            default => $viewer->hasAdminPermission('orders.view') || $viewer->hasAdminPermission('orders.manage'),
        };
    }

    private function orders(Customer $customer, User $viewer): Builder
    {
        return Order::where('customer_id', $customer->id)
            ->when(! $viewer->isCustomer() && $viewer->isWarehouseOnly(), fn ($q) => $q->visibleToWarehouse($viewer->id));
    }

    private function query(Customer $customer, User $viewer, string $kind): Builder
    {
        $orders = $this->orders($customer, $viewer)->select('id');
        return match ($kind) {
            'order', 'initial-payment' => $this->orders($customer, $viewer)->with('payment'),
            'transfer-proof' => $this->orders($customer, $viewer)->whereNotNull('transfer_receipt_path'),
            'invoice' => CustomerInvoice::whereIn('order_id', $orders)->with('order:id,number'),
            'refund' => \App\Models\CustomerRefundEvent::whereHas('refund',fn($q)=>$q->where('customer_id',$customer->id))->with('refund:id,number,customer_id'),
            'credit-note' => \App\Models\OrderCredit::where('customer_id',$customer->id)->with('order:id,number'),
            'settlement', 'settlement-proof' => OrderSettlement::whereIn('order_id', $orders)->with('order:id,number')
                ->when($kind === 'settlement-proof', fn ($q) => $q->whereNotNull('receipt_path')),
            'account-receipt' => CustomerReceiptEvent::whereHas('receipt', fn ($q) => $q->where('customer_id', $customer->id))->with('receipt:id,customer_id,number'),
            'account-proof' => CustomerReceiptLine::whereHas('receipt', fn ($q) => $q->where('customer_id', $customer->id))->whereNotNull('proof_path')->with('receipt:id,customer_id,number'),
            'quotation' => Quotation::where('customer_id', $customer->id),
            'email-copy' => EmailHistory::whereIn('order_id', $orders)->whereIn('type', ['order_confirmation', 'payment_received'])
                ->where('status', 'accepted')->whereNotNull('archived_at')->whereNotNull('archive')->with('order:id,number'),
        };
    }

    private function columns(string $kind): array
    {
        return match ($kind) {
            'order', 'initial-payment' => ['id', 'number', 'ordered_at', 'created_at', 'paid_at', 'manual_collected_cents', 'payment_status', 'subtotal_cents', 'delivery_cents', 'tax_cents'],
            'transfer-proof' => ['id', 'number', 'created_at', 'transfer_receipt_uploaded_at', 'transfer_receipt_path'],
            'invoice' => ['id', 'order_id', 'number', 'issued_at'],
            'refund' => ['id','customer_refund_id','action','created_at'],
            'credit-note' => ['id','order_id','number','created_at'],
            'settlement', 'settlement-proof' => ['id', 'order_id', 'amount_cents', 'entry_kind', 'created_at', 'receipt_path'],
            'account-receipt' => ['id', 'customer_receipt_id', 'action', 'created_at'],
            'account-proof' => ['id', 'customer_receipt_id', 'proof_path', 'proof_name', 'created_at'],
            'quotation' => ['id', 'created_at'],
            'email-copy' => ['id', 'order_id', 'type', 'sent_at', 'created_at'],
        };
    }

    private function describe(string $kind, Model $record): ?array
    {
        $date = $record->created_at;
        $reference = $record->order?->number ?? '';
        $note = '';
        $format = 'PDF';
        switch ($kind) {
            case 'refund':
                $name='Refund / cheque record — '.$record->refund->number.' · version #'.$record->id; $reference=$record->refund->number; $note='Saved version. Pending cheques are not confirmed refunds.'; break;
            case 'credit-note':
                $name='Credit note — '.$record->number; $note='Adjustment to the original sale, linked to a confirmed refund.'; break;
            case 'order':
                $name = 'Order PDF — '.$record->number; $reference = $record->number; $date = $record->ordered_at; break;
            case 'invoice':
                $name = 'Invoice — '.$record->label; $date = $record->issued_at; $note = 'Saved invoice amounts; current balances are labelled separately.'; break;
            case 'initial-payment':
                if ($record->collected_cents <= 0) return null;
                $name = 'Payment receipt — PAY-'.$record->number; $reference = $record->number;
                $date = $record->paid_at ?? $record->payment?->paid_at ?? $record->ordered_at;
                $note = 'Original recorded collection. Later corrections are separate records.'; break;
            case 'settlement':
                $name = $record->entry_label.' — RCPT-'.$record->id; break;
            case 'account-receipt':
                $name = 'Receipt / cheque record — '.$record->receipt->number.' · version #'.$record->id;
                $reference = $record->receipt->number; $note = ucfirst($record->action).' · saved version; uncleared cheques are not confirmed payments.'; break;
            case 'quotation':
                $name = 'Quotation — '.$record->number; $reference = $record->number; break;
            case 'email-copy':
                $name = 'Saved email PDF — '.($record->type === 'order_confirmation' ? 'Order confirmation' : 'Payment receipt').' #'.$record->id;
                $date = $record->sent_at; $note = 'Exact PDF retained from the email.'; break;
            default:
                $path = $this->filePath($kind, $record);
                if (! $path || ! Storage::disk('local')->exists($path)) return null;
                $format = strtoupper(pathinfo($path, PATHINFO_EXTENSION)) ?: 'File';
                $name = match ($kind) {
                    'transfer-proof' => 'Payment proof — '.$record->number,
                    'settlement-proof' => 'Payment attachment — RCPT-'.$record->id,
                    'account-proof' => $record->proof_name ?: 'Payment attachment — '.$record->receipt->number.' line #'.$record->id,
                };
                $reference = $kind === 'transfer-proof' ? $record->number : ($kind === 'account-proof' ? $record->receipt->number : $reference);
                if ($kind === 'transfer-proof') $date = $record->transfer_receipt_uploaded_at ?? $date;
                $note = 'Uploaded supporting file; not a confirmation of payment.';
        }
        return ['kind' => $kind, 'id' => $record->id, 'group' => self::KINDS[$kind], 'name' => $name,
            'reference' => $reference, 'date' => $date, 'format' => $format, 'note' => $note];
    }

    public function filePath(string $kind, Model $record): ?string
    {
        return match ($kind) {
            'transfer-proof' => $record->transfer_receipt_path,
            'settlement-proof' => $record->receipt_path,
            'account-proof' => $record->proof_path,
            default => null,
        };
    }

    public function listing(Customer $customer, User $viewer, Request $request): LengthAwarePaginator
    {
        $this->authorizeCustomer($customer, $viewer);
        $filters = $request->validate(['document_search' => 'nullable|string|max:150',
            'document_type' => 'nullable|in:'.implode(',', array_keys(self::GROUPS)), 'documents_page' => 'nullable|integer|min:1']);
        $search = mb_strtolower(trim($filters['document_search'] ?? ''));
        $documents = collect();
        foreach (self::KINDS as $kind => $group) {
            if (! $this->allowed($viewer, $kind) || (! empty($filters['document_type']) && $filters['document_type'] !== $group)) continue;
            // Do not load encrypted emails, invoice snapshots or receipt snapshots for the list.
            foreach ($this->query($customer, $viewer, $kind)->get($this->columns($kind)) as $record) {
                $entry = $this->describe($kind, $record);
                if ($entry && ($search === '' || str_contains(mb_strtolower($entry['name'].' '.$entry['reference'].' '.self::GROUPS[$group]), $search))) $documents->push($entry);
            }
        }
        $documents = $documents->sortByDesc(fn ($entry) => ($entry['date'] ? \Illuminate\Support\Carbon::parse($entry['date'])->format('Y-m-d H:i:s') : '').':'.$entry['kind'].':'.str_pad($entry['id'], 20, '0', STR_PAD_LEFT))->values();
        $page = (int) ($filters['documents_page'] ?? 1);
        return (new LengthAwarePaginator($documents->forPage($page, 20)->values(), $documents->count(), 20, $page,
            ['path' => $request->url(), 'pageName' => 'documents_page']))->withQueryString()->fragment('customer-documents');
    }

    private function authorizeCustomer(Customer $customer, User $viewer): void
    {
        abort_unless($viewer->is_active, 403);
        if ($viewer->isCustomer()) abort_unless((int) $customer->user_id === (int) $viewer->id, 404);
        else abort_unless($viewer->hasAdminPermission('orders.view') || $viewer->hasAdminPermission('customers.manage'), 403);
    }

    public function resolve(Customer $customer, User $viewer, string $kind, int $id): array
    {
        $this->authorizeCustomer($customer, $viewer);
        abort_unless(isset(self::KINDS[$kind]), 404);
        abort_unless($this->allowed($viewer, $kind), 403);
        $record = $this->query($customer, $viewer, $kind)->findOrFail($id);
        $entry = $this->describe($kind, $record);
        abort_unless($entry, 404);
        return [$record, $entry];
    }
}
