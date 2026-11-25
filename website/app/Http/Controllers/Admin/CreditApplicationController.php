<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{CreditApplication, Customer};
use App\Services\{CreditApplicationDocuments, CreditApplications};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\HeaderUtils;

class CreditApplicationController extends Controller
{
    public function index(Request $request, CreditApplications $service)
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(CreditApplication::STATUSES))],
            'customer_id' => 'nullable|integer|min:1',
        ]);
        $status = $filters['status'] ?? null;
        $customer = isset($filters['customer_id']) ? Customer::findOrFail($filters['customer_id']) : null;
        $active = $service->activeTemplate();
        $applications = CreditApplication::with(['customer:id,name,business_name,email', 'template:id,title', 'invitationEmail:id,status'])
            ->when($customer, fn ($query, $customer) => $query->where('customer_id', $customer->id))
            ->when($status, fn ($query, $status) => $query->where('status', $status))
            ->orderByRaw("CASE WHEN status = 'submitted' THEN 0 ELSE 1 END")->latest('id')
            ->paginate(25, ['id', 'customer_id', 'template_id', 'status', 'invitation_email_id', 'submitted_at', 'created_at'])->withQueryString();

        return view('admin.credit-applications.index', compact('status', 'applications', 'customer', 'active'));
    }

    public function invite(Request $request, CreditApplications $service)
    {
        $data = $request->validate(['customer_id' => 'required|integer|exists:customers,id']);
        $application = $service->start($request->user(), Customer::findOrFail($data['customer_id']));

        return redirect()->route('admin.credit-applications.show', $application)
            ->with('status', $application->wasRecentlyCreated ? 'Invitation queued to the customer’s verified email.' : 'An invitation is already on record for this application.');
    }

    public function show(CreditApplication $application)
    {
        $application->load(['customer', 'template', 'invitationEmail:id,status,sent_at']);

        return view('admin.credit-applications.show', ['application' => $application]);
    }

    public function review(Request $request, CreditApplication $application, CreditApplications $service)
    {
        $data = $request->validate([
            'status' => 'required|in:approved,rejected', 'review_note' => 'required_if:status,rejected|nullable|string|max:1000',
        ]);
        $service->review($application, $request->user(), $data);

        return redirect()->route('admin.credit-applications.show', $application)->with('status', 'Application '.$data['status'].'.');
    }

    public function document(Request $request, CreditApplication $application, CreditApplicationDocuments $documents)
    {
        abort_unless($application->submitted_at, 404);
        $filename = strtolower($application->reference).'-signed.pdf';

        return response($documents->signedBytes($application), 200, [
            'Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => HeaderUtils::makeDisposition($request->boolean('download') ? 'attachment' : 'inline', $filename),
        ]);
    }
}
