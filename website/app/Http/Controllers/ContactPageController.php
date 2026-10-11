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
            'show_email' => 'sometimes|boolean', 'show_phone' => 'sometimes|boolean',
            'links_present' => 'sometimes|accepted', 'links' => 'sometimes|array|max:100',
            'links.*.id' => 'nullable|string|max:80|distinct',
            'links.*.label' => 'required|string|max:80',
            'links.*.url' => 'required|url:http,https|max:2000',
            'links.*.active' => 'required|boolean', 'links.*.sort' => 'required|integer|min:0|max:9999',
            'links.*.icon' => 'nullable|file|image|mimes:jpg,jpeg,png,webp|max:1024',
            'links.*.remove_icon' => 'sometimes|boolean',
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
        $newIcons = [];
        $retiredIcons = [];
        try {
            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('contact', 'local');
                if (! $path) {
                    throw new \RuntimeException('Unable to save image.');
                }
            }
            DB::transaction(function () use ($data, $path, &$old, &$newIcons, &$retiredIcons) {
                $settings = GeneralSetting::lockForUpdate()->findOrFail(1);
                if ($settings->revision !== (int) $data['revision']) {
                    throw ValidationException::withMessages(['settings' => 'Settings changed. Reload before saving.']);
                }
                $values = collect($data)->except(['image', 'revision', 'links', 'links_present'])->all();
                if (isset($data['links_present'])) {
                    $existing = collect($settings->contactLinks())->keyBy('id');
                    $links = [];
                    foreach ($data['links'] ?? [] as $index => $item) {
                        $id = $item['id'] ?? null;
                        if ($id && !$existing->has($id)) {
                            throw ValidationException::withMessages(["links.$index.id" => 'This link changed. Reload before saving.']);
                        }
                        $icon = $id ? ($existing[$id]['icon'] ?? null) : null;
                        if (!empty($item['remove_icon'])) $icon = null;
                        if (!empty($item['icon'])) {
                            $icon = $item['icon']->store('contact-icons', 'local');
                            if (!$icon) throw new \RuntimeException('Unable to save link icon.');
                            $newIcons[] = $icon;
                        }
                        $links[] = ['id' => $id ?: (string) \Illuminate\Support\Str::uuid(), 'label' => $item['label'], 'url' => $item['url'], 'active' => (bool) $item['active'], 'sort' => (int) $item['sort'], 'icon' => $icon];
                    }
                    $retiredIcons = array_values(array_diff($existing->pluck('icon')->filter()->all(), array_filter(array_column($links, 'icon'))));
                    $values['contact_links'] = $links;
                }
                $values['contact_eyebrow'] = $data['contact_eyebrow'] ?? '';
                $values['revision'] = $settings->revision + 1;
                if ($path) {
                    $old = $settings->contact_image_path;
                    $values['contact_image_path'] = $path;
                }
                $settings->update($values);
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($newIcons);
            if ($path) {
                Storage::disk('local')->delete($path);
            }throw $e;
        }
        if ($old) {
            Storage::disk('local')->delete($old);
        }
        Storage::disk('local')->delete($retiredIcons);

        return redirect()->route('admin.contact.edit')->with('status', 'Contact Us saved. Your contact page and footer are updated.');
    }

    public function linkIcon(string $id)
    {
        $settings = GeneralSetting::findOrFail(1);
        $link = collect($settings->contactLinks())->firstWhere('id', $id);
        abort_unless($link && ($link['active'] || auth()->user()?->hasAdminPermission('contact.manage')), 404);
        abort_unless(!empty($link['icon']) && Storage::disk('local')->exists($link['icon']), 404);
        return response()->file(Storage::disk('local')->path($link['icon']), ['Cache-Control' => 'no-cache', 'X-Content-Type-Options' => 'nosniff']);
    }
}
