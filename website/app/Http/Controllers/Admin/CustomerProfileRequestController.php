<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerProfileRequest;
use App\Services\CustomerProfileChanges;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerProfileRequestController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['status' => Rule::in(array_keys(CustomerProfileRequest::STATUSES))]);
        $status = $filters['status'] ?? 'pending';
        $changes = CustomerProfileRequest::with('customer')->where('status', $status)->latest('id')->paginate(25)->withQueryString();

        return response()->view('admin.customer-profile-requests.index', compact('status', 'changes'))->header('Cache-Control', 'no-store, private');
    }

    public function show(CustomerProfileRequest $change)
    {
        return response()->view('admin.customer-profile-requests.show', compact('change'))->header('Cache-Control', 'no-store, private');
    }

    public function review(Request $request, CustomerProfileRequest $change, CustomerProfileChanges $profiles)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected'])],
            'review_note' => 'required_if:status,rejected|nullable|string|max:1000',
        ]);
        $profiles->review($change, $request->user(), $data);

        return redirect()->route('admin.profile-requests.show', $change)->with('status', 'Profile request '.$data['status'].'.');
    }
}
