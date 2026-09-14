<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BannerController extends Controller
{
    public function index()
    {
        return view('admin.banners.index', ['banners' => Banner::orderBy('priority')->orderBy('id')->paginate(20)]);
    }

    public function create()
    {
        return view('admin.banners.form', ['banner' => new Banner(['priority' => (int) Banner::max('priority') + 1, 'is_active' => true, 'button_text' => 'Explore the Menu'])]);
    }

    public function edit(Banner $banner)
    {
        return view('admin.banners.form', compact('banner'));
    }

    public function store(Request $r)
    {
        return $this->save($r, new Banner);
    }

    public function update(Request $r, Banner $banner)
    {
        return $this->save($r, $banner);
    }

    private function save(Request $r, Banner $banner)
    {
        $data = $r->validate(['heading' => 'required|string|max:160', 'subheading' => 'required|string|max:255', 'button_text' => 'required|string|max:80', 'alt_text' => 'required|string|max:255', 'priority' => 'required|integer|min:1|max:100000', 'is_active' => 'required|boolean', 'image' => [$banner->exists ? 'nullable' : 'required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240']]);
        unset($data['image']);
        $path = null;
        $old = null;
        try {
            if ($r->hasFile('image')) {
                $path = $r->file('image')->store('banners', 'local');
                if (! $path) {
                    throw new \RuntimeException('Unable to store the banner image.');
                }$data['image_path'] = $path;
                $data['asset_path'] = null;
            }
            DB::transaction(function () use ($banner, $data, &$old) {
                if ($banner->exists) {
                    $banner->setRawAttributes(Banner::lockForUpdate()->findOrFail($banner->id)->getAttributes(), true);
                    $old = $banner->image_path;
                }$banner->fill($data)->save();
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }throw $e;
        }
        if ($path && $old) {
            Storage::disk('local')->delete($old);
        }

        return redirect()->route('admin.banners.index')->with('status', 'Banner saved. Active banners appear on the home page in priority order.');
    }

    public function image(Banner $banner)
    {
        abort_unless($banner->is_active || auth()->user()?->hasAdminPermission('banners.view'), 404);
        abort_unless($banner->image_path && Storage::disk('local')->exists($banner->image_path), 404);

        return response()->file(Storage::disk('local')->path($banner->image_path), ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-cache']);
    }
}
