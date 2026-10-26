<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\DocumentTheme;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;

class CustomerFileCoverController extends Controller
{
    public function __invoke(Request $request, Customer $customer, DocumentTheme $theme)
    {
        $input = $request->validate([
            'year' => 'required|string|max:20',
            'volume' => 'required|string|max:20',
        ]);
        // These values belong only to this print request; no customer fields are saved.
        $cover = [
            'brand' => $theme->brand(),
            'id' => (string) $customer->id,
            'name' => mb_strtoupper(trim($customer->business_name ?: $customer->name)),
            'contact' => $customer->name,
            'phone' => $customer->business_phone ?: $customer->phone,
            'email' => $customer->business_email ?: $customer->email,
            'address' => implode(', ', array_filter(array_map(
                fn ($value) => trim((string) $value),
                [$customer->address, $customer->city, $customer->province, $customer->postal_code, $customer->country]
            ))),
            'year' => $input['year'],
            'volume' => $input['volume'],
        ];
        $renderer = new Dompdf(new Options([
            'isRemoteEnabled' => false, 'isPhpEnabled' => false,
            'defaultFont' => 'DejaVu Sans', 'defaultMediaType' => 'print',
        ]));
        $renderer->loadHtml(view('documents.customer-file-cover', compact('cover'))->render());
        $renderer->setPaper('A4', 'portrait');
        $renderer->render();

        return response($renderer->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="customer-file-'.$customer->id.'.pdf"',
        ]);
    }
}
