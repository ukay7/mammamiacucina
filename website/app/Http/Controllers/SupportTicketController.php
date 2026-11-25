<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use App\Services\CustomerEmails;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SupportTicketController extends Controller
{
    public function index(Request $request)
    {
        $staff = ! $request->user()->isCustomer();
        $data = $request->validate(['status' => ['nullable', Rule::in(array_keys(SupportTicket::STATUSES))]]);
        $status = $data['status'] ?? '';
        $tickets = SupportTicket::query()
            ->when($staff, fn ($query) => $query->with('customer:id,name,business_name'),
                fn ($query) => $query->where('customer_id', $request->user()->customerRecord()->id))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest('updated_at')->latest('id')->paginate(20)->withQueryString();

        return view('customer.support.index', compact('staff', 'tickets', 'status'));
    }

    public function store(Request $request, CustomerEmails $emails)
    {
        $data = $request->validate(['subject' => 'required|string|max:150', 'body' => 'required|string|max:6000']);
        $customer = $request->user()->customerRecord();
        $ticket = DB::transaction(function () use ($request, $data, $customer, $emails) {
            $ticket = SupportTicket::create(['customer_id' => $customer->id, 'subject' => $data['subject']]);
            $ticket->refresh();
            $message = $this->message($ticket, $request, 'customer', $data['body']);
            $emails->queue('support-request:'.$message->id, 'support_request', config('mail.order_notification_address'), [
                'ticket' => $ticket->label, 'subject' => $ticket->subject, 'customer_name' => $customer->name,
                'customer_email' => $request->user()->email, 'message' => $message->body,
                'ticket_url' => route('admin.support.show', $ticket),
            ]);

            return $ticket;
        });

        return redirect()->route('customer.support.show', $ticket)->with('status', 'Your request was submitted.');
    }

    public function show(Request $request, SupportTicket $ticket)
    {
        $staff = $this->authorizeTicket($request, $ticket);
        $messages = $ticket->messages()->when(! $staff, fn ($query) => $query->where('kind', '!=', 'note'))
            ->latest('id')->paginate(30);

        return view('customer.support.show', compact('ticket', 'staff', 'messages'));
    }

    public function reply(Request $request, SupportTicket $ticket)
    {
        $staff = $this->authorizeTicket($request, $ticket);
        $data = $request->validate(['body' => 'required|string|max:6000', 'internal' => $staff ? 'sometimes|boolean' : 'prohibited']);
        DB::transaction(function () use ($request, $ticket, $staff, $data) {
            $ticket = SupportTicket::lockForUpdate()->findOrFail($ticket->id);
            $this->message($ticket, $request, $staff ? (($data['internal'] ?? false) ? 'note' : 'staff') : 'customer', $data['body']);
            if (! $staff && $ticket->status === 'resolved') {
                $ticket->status = 'pending';
                $this->message($ticket, $request, 'status', 'Reopened after a customer reply.');
            }
            $ticket->touch();
        });

        return redirect()->route($staff ? 'admin.support.show' : 'customer.support.show', $ticket)->with('status', 'Message saved.');
    }

    public function status(Request $request, SupportTicket $ticket)
    {
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(SupportTicket::STATUSES))]]);
        DB::transaction(function () use ($request, $ticket, $data) {
            $ticket = SupportTicket::lockForUpdate()->findOrFail($ticket->id);
            if ($ticket->status !== $data['status']) {
                $this->message($ticket, $request, 'status', SupportTicket::STATUSES[$ticket->status].' → '.SupportTicket::STATUSES[$data['status']]);
                $ticket->update($data);
            }
        });

        return back()->with('status', 'Ticket status updated.');
    }

    private function authorizeTicket(Request $request, SupportTicket $ticket): bool
    {
        if (! $request->user()->isCustomer()) return true;
        abort_unless((int) $ticket->customer_id === $request->user()->customerRecord()->id, 404);

        return false;
    }

    private function message(SupportTicket $ticket, Request $request, string $kind, string $body)
    {
        return $ticket->messages()->create([
            'user_id' => $request->user()->id, 'author_name' => $request->user()->name, 'kind' => $kind, 'body' => $body,
        ]);
    }
}
