<?php

namespace App\Http\Controllers;

use App\Models\AboutPage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AboutPageController extends Controller
{
    public function show()
    {
        return view('pages.about', ['about' => AboutPage::findOrFail(1)]);
    }

    public function image()
    {
        $about = AboutPage::findOrFail(1);
        abort_unless($about->image_path && Storage::disk('local')->exists($about->image_path), 404);

        return response()->file(Storage::disk('local')->path($about->image_path), ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'no-cache']);
    }

    public function edit()
    {
        return view('admin.settings.about', ['about' => AboutPage::findOrFail(1)]);
    }

    public function update(Request $request)
    {
        $data = $request->validate(['quote_supporting_text'=>'sometimes|nullable|string|max:2000','closing_sentence'=>'sometimes|nullable|string|max:2000','revision' => 'required|integer|min:0', 'eyebrow' => 'nullable|string|max:100', 'heading' => 'required|string|max:255',
            'description' => 'required|string|max:15000', 'image' => 'nullable|file|image|mimes:png,jpg,jpeg,webp|max:5120', 'image_alt' => 'required|string|max:255',
            'button_name' => 'required|string|max:80', 'button_page' => 'required|in:product-grid,contact,gallery', 'items' => 'sometimes|array|max:100',
            'items.*' => 'required|array:title,description', 'items.*.title' => 'required|string|max:120', 'items.*.description' => 'required|string|max:2000']);
        $path = null;
        $old = null;
        try {
            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('about', 'local');
                if (! $path) {
                    throw new \RuntimeException('Image could not be stored.');
                }
            }
            DB::transaction(function () use ($data, $path, &$old) {
                $about = AboutPage::lockForUpdate()->findOrFail(1);
                if ($about->revision !== (int) $data['revision']) {
                    throw ValidationException::withMessages(['about' => 'This page changed. Reload before saving.']);
                }
                $values = collect($data)->except(['image', 'revision'])->all();
                $values['eyebrow'] = $data['eyebrow'] ?? '';
                if (array_key_exists('items',$data)) $values['items'] = array_values($data['items']);
                $values['revision'] = $about->revision + 1;
                if ($path) {
                    $old = $about->image_path;
                    $values['image_path'] = $path;
                }
                $about->update($values);
            }, 3);
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }throw $e;
        }
        if ($old) {
            Storage::disk('local')->delete($old);
        }

        return redirect()->route('admin.about.edit')->with('status', 'About Us saved. Your website is updated.');
    }
}
