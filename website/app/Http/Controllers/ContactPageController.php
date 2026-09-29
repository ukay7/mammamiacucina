<?php

namespace App\Http\Controllers;

use App\Models\GeneralSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ContactPageController extends Controller
{
    public function edit()
    {
        return view('admin.settings.contact', ['settings' => GeneralSetting::findOrFail(1)]);
    }

    public function image()
    {
        $settings = GeneralSetting::findOrFail(1);
        abort_unless($settings->contact_image_path && Storage::disk('local')->exists($settings->contact_image_path), 404);

        return response()->file(Storage::disk('local')->path($settings->contact_image_path), ['Cache-Control' => 'no-cache', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'revision' => 'required|integer|min:0', 'contact_eyebrow' => 'nullable|string|max:255', 'contact_heading' => 'required|string|max:255', 'contact_description' => 'required|string|max:15000', 'contact_image_alt' => 'required|string|max:255',
            'image' => 'nullable|file|image|mimes:jpg,jpeg,png,webp|max:5120',
            'email' => 'nullable|email|max:255', 'phone' => ['nullable', 'string', 'max:60', 'regex:/^[0-9+(). x-]+$/i'],
            'whatsapp_number' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9 ()-]+$/'],
            'facebook_url' => 'nullable|url:http,https|max:500', 'instagram_url' => 'nullable|url:http,https|max:500', 'website_url' => 'nullable|url:http,https|max:500',
        ]);
        if (! empty($data['whatsapp_number'])) {
            $digits = preg_replace('/\D/', '', $data['whatsapp_number']);
            if (! preg_match('/^[1-9][0-9]{6,14}$/', $digits)) {
                throw ValidationException::withMessages(['whatsapp_number' => 'Enter an international WhatsApp number including country code (7–15 digits).']);
            }
            $data['whatsapp_number'] = '+'.$digits;
        }
        $path = null;
        $old = null;
        try {
            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('contact', 'local');
                if (! $path) {
                    throw new \RuntimeException('Unable to save image.');
                }
            }
            DB::transaction(function () use ($data, $path, &$old) {
                $settings = GeneralSetting::lockForUpdate()->findOrFail(1);
                if ($settings->revision !== (int) $data['revision']) {
                    throw ValidationException::withMessages(['settings' => 'Settings changed. Reload before saving.']);
                }
                $values = collect($data)->except(['image', 'revision'])->all();
                $values['contact_eyebrow'] = $data['contact_eyebrow'] ?? '';
                $values['revision'] = $settings->revision + 1;
                if ($path) {
                    $old = $settings->contact_image_path;
                    $values['contact_image_path'] = $path;
                }
                $settings->update($values);
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }throw $e;
        }
        if ($old) {
            Storage::disk('local')->delete($old);
        }

        return redirect()->route('admin.contact.edit')->with('status', 'Contact Us saved. Your contact page and footer are updated.');
    }
}
