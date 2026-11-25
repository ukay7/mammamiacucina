<?php

namespace App\Http\Controllers;

use App\Models\CreditApplication;
use App\Services\{CreditApplicationDocuments, CreditApplications};
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\HeaderUtils;

class CreditApplicationController extends Controller
{
    public function open(Request $request, string $application)
    {
        $application = filter_var($application, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        abort_if($application === false, 404);
        if (! $request->user()) {
            $request->session()->put('credit_application_after_login', $application);
            return redirect()->route('customer.login');
        }

        return redirect()->route('customer.credit-applications.show', $application);
    }

    public function index(Request $request, CreditApplications $service)
    {
        $customer = $request->user()->customerRecord();
        $active = $service->activeTemplate();
        $applications = $customer->creditApplications()->with('template:id,title')->latest('id')
            ->paginate(15, ['id', 'template_id', 'status', 'created_at', 'submitted_at']);
        $awaiting = $customer->creditApplications()->where('status', 'awaiting_signature')->latest('id')->first(['id', 'template_id']);
        $underReview = $customer->creditApplications()->where('status', 'submitted')->exists();

        return view('customer.credit-applications.index', compact('active', 'applications', 'awaiting', 'underReview'));
    }

    public function start(Request $request, CreditApplications $service)
    {
        $application = $service->start($request->user(), $request->user()->customerRecord());

        return redirect()->route('customer.credit-applications.show', $application)
            ->with('status', $application->wasRecentlyCreated ? 'Application started. A link to the form was queued for your verified email.' : 'Your application is ready to continue.');
    }

    public function show(Request $request, CreditApplication $application, CreditApplicationDocuments $documents)
    {
        $this->own($request, $application);
        $customer = $application->customer;
        $template = $application->template;
        $editable = $application->status === 'awaiting_signature' && $template->published_at && ! $template->retired_at && $template->legal_approval_reference;
        $draft = $editable ? $application->draft_details : null;
        $preview = $draft && $request->boolean('review') ? $documents->draft($application) : null;

        return view('customer.credit-applications.show', compact('application', 'customer', 'template', 'editable', 'draft', 'preview'));
    }

    public function prepare(Request $request, CreditApplication $application, CreditApplications $service)
    {
        $this->own($request, $application);
        $data = $request->validate([
            'applicant_name' => 'required|string|max:150', 'contact_name' => 'required|string|max:150',
            'contact_phone' => 'required|string|max:40', 'billing_address' => 'required|string|max:400',
            'requested_limit' => ['required', 'regex:/^\d{1,7}(\.\d{1,2})?$/D', 'numeric', 'gt:0'],
            'guarantor_name' => 'required|string|max:150', 'guarantor_address' => 'required|string|max:400',
            'signing_title' => 'nullable|string|max:100',
        ]);
        $data['requested_limit'] = number_format((float) $data['requested_limit'], 2, '.', '');
        $service->prepare($application, $request->user(), $data);

        return redirect()->route('customer.credit-applications.show', [$application, 'review' => 1]);
    }

    public function submit(Request $request, CreditApplication $application, CreditApplications $service, CreditApplicationDocuments $documents)
    {
        $this->own($request, $application);
        $data = $request->validate([
            'review_token' => 'required|string|size:64', 'signature' => 'required|string|max:150',
            'consent' => 'required|accepted', 'password' => 'required|string|max:255',
        ]);
        $service->submit($application, $request->user(), $data, $documents);

        return redirect()->route('customer.credit-applications.show', $application)->with('status', 'Signed application submitted for review.');
    }

    public function draft(Request $request, CreditApplication $application, CreditApplicationDocuments $documents)
    {
        $this->own($request, $application);
        abort_unless($application->status === 'awaiting_signature' && $application->draft_details
            && $application->template->published_at && ! $application->template->retired_at
            && $application->template->legal_approval_reference, 404);
        $filename = 'unsigned-'.strtolower($application->reference).'.pdf';

        return response($documents->render($documents->draft($application)), 200, [
            'Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => HeaderUtils::makeDisposition('inline', $filename),
        ]);
    }

    private function own(Request $request, CreditApplication $application): void
    {
        abort_unless((int) $application->customer_id === $request->user()->customerRecord()->id, 404);
    }
}
