<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CreditApplicationTemplate;
use App\Services\CreditApplications;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreditApplicationTemplateController extends Controller
{
    public function index()
    {
        return view('admin.credit-applications.templates', ['templates' => CreditApplicationTemplate::latest('id')->paginate(25)]);
    }

    public function create()
    {
        return view('admin.credit-applications.template-edit', ['template' => new CreditApplicationTemplate(['title' => 'Credit Application & Personal Guarantee'])]);
    }

    public function save(Request $request, ?CreditApplicationTemplate $template = null)
    {
        $data = $request->validate([
            'title' => 'required|string|max:100',
            'application_terms' => 'required|string|max:12000',
            'guarantee_terms' => 'required|string|max:12000',
            'consent_statement' => 'required|string|max:2000',
            'revision' => $template?->exists ? 'required|integer|min:0' : 'exclude',
        ]);
        if (! $template?->exists) {
            $template = CreditApplicationTemplate::create($data);
        } else {
            DB::transaction(function () use ($template, $data) {
                $current = CreditApplicationTemplate::lockForUpdate()->findOrFail($template->id);
                if ($current->published_at || $current->revision !== (int) $data['revision']) {
                    throw ValidationException::withMessages(['template' => 'The draft changed or was published. Reload the template before editing.']);
                }
                $current->update(array_replace($data, ['revision' => $current->revision + 1]));
            }, 3);
        }

        return redirect()->route('admin.credit-applications.templates.show', $template)->with('status', 'Template draft saved.');
    }

    public function show(CreditApplicationTemplate $template)
    {
        return view('admin.credit-applications.template-show', compact('template'));
    }

    public function edit(CreditApplicationTemplate $template)
    {
        abort_if($template->published_at, 403);
        return view('admin.credit-applications.template-edit', compact('template'));
    }

    public function publish(Request $request, CreditApplicationTemplate $template, CreditApplications $service)
    {
        $data = $request->validate([
            'approved' => 'required|accepted', 'legal_approval_reference' => 'required|string|max:255',
            'revision' => 'required|integer|min:0',
        ]);
        $service->publish($template, (int) $data['revision'], $data['legal_approval_reference'], $request->user());

        return redirect()->route('admin.credit-applications.templates.show', $template)->with('status', 'Template published for electronic signature.');
    }

    public function retire(CreditApplicationTemplate $template, CreditApplications $service)
    {
        $service->retire($template);

        return redirect()->route('admin.credit-applications.templates.show', $template)->with('status', 'Template retired.');
    }
}
