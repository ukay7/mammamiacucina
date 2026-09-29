<?php

namespace App\Http\Controllers;

use App\Models\CataloguePage;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CatalogueBookController extends Controller
{
    public function show()
    {
        return view('pages.catalogue', ['hasPages' => CataloguePage::where('is_active', true)->exists()]);
    }

    public function reader()
    {
        $pages = CataloguePage::with('category')->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();
        if ($pages->isEmpty()) {
            return response('The catalogue is being prepared.', 200);
        }
        $sections = [];
        foreach ($pages as $i => $page) {
            $key = $page->category_id ? 'category-'.$page->category_id : 'label-'.Str::lower($page->custom_label);
            if (! isset($sections[$key])) {
                $sections[$key] = ['name' => $page->label(), 'page' => $i + 1, 'num' => str_pad((string) (count($sections) + 1), 2, '0', STR_PAD_LEFT), 'indices' => []];
            }
            $sections[$key]['indices'][] = $i;
        }

        return view('pages.catalogue-reader', ['catalogue' => ['count' => $pages->count(), 'titles' => $pages->pluck('title')->all(), 'pages' => $pages->map(fn ($p) => $this->imageUrl($p))->all(), 'thumbs' => $pages->map(fn ($p) => $this->imageUrl($p, true))->all(), 'sections' => array_values($sections)]]);
    }

    private function imageUrl(CataloguePage $page, bool $thumbnail = false): string
    {
        if ($page->image_path) {
            return route('catalogue.image', [$page, 'thumb' => $thumbnail ? 1 : 0, 'v' => $page->revision]);
        }
        $path = $thumbnail ? ($page->default_thumbnail ?? $page->default_image) : $page->default_image;

        return asset($path).'?v='.filemtime(public_path($path));
    }

    public function image(Request $request, CataloguePage $page)
    {
        abort_unless($page->is_active, 404);

        return $this->file($request, $page);
    }

    public function preview(Request $request, CataloguePage $page)
    {
        return $this->file($request, $page);
    }

    private function file(Request $request, CataloguePage $page)
    {
        $path = $page->image_path ? Storage::disk('local')->path($page->image_path) : public_path($request->boolean('thumb') ? ($page->default_thumbnail ?? $page->default_image) : $page->default_image);
        abort_unless(is_file($path), 404);

        $versioned = ! $request->routeIs('admin.*') && (string) $request->query('v') === (string) $page->revision;
        $response = response()->file($path, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => $versioned ? 'private, max-age=86400' : 'private, no-cache']);
        $response->setPrivate();
        $response->setEtag(hash('sha256', $path.'|'.$page->revision.'|'.filesize($path).'|'.filemtime($path)));
        $response->setLastModified(new \DateTimeImmutable('@'.filemtime($path)));
        $response->isNotModified($request);

        return $response;
    }

    public function index()
    {
        return view('admin.catalogue.index', ['pages' => CataloguePage::with('category')->orderBy('sort_order')->orderBy('id')->get()]);
    }

    public function create()
    {
        return $this->form(new CataloguePage(['is_active' => true, 'sort_order' => (CataloguePage::max('sort_order') ?? 0) + 10, 'revision' => 0]));
    }

    public function edit(CataloguePage $page)
    {
        return $this->form($page);
    }

    private function form(CataloguePage $page)
    {
        return view('admin.catalogue.form', ['page' => $page, 'categories' => Category::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        return $this->save($request, new CataloguePage);
    }

    public function update(Request $request, CataloguePage $page)
    {
        return $this->save($request, $page);
    }

    private function save(Request $request, CataloguePage $page)
    {
        $data = $request->validate(['title' => 'required|string|max:255', 'category_id' => 'nullable|exists:categories,id', 'custom_label' => 'required_without:category_id|nullable|string|max:255', 'image' => ($page->exists ? 'nullable' : 'required').'|file|image|mimes:jpg,jpeg,png,webp|max:10240', 'sort_order' => 'required|integer|min:0|max:1000000', 'is_active' => 'required|boolean', 'revision' => 'required|integer|min:0']);
        $path = null;
        $old = null;
        try {
            if ($request->hasFile('image')) {
                $path = $request->file('image')->store('catalogue-pages', 'local');
                if (! $path) {
                    throw new \RuntimeException('Could not store image.');
                }
            }
            DB::transaction(function () use ($page, $data, $path, &$old) {
                if ($page->exists) {
                    $page = CataloguePage::lockForUpdate()->findOrFail($page->id);
                    $this->checkRevision($page, (int) $data['revision']);
                }
                $values = collect($data)->except(['image', 'revision'])->all();
                $values['category_id'] = $data['category_id'] ?? null;
                $values['custom_label'] = $values['category_id'] ? Category::findOrFail($values['category_id'])->name : $data['custom_label'];
                $values['revision'] = $page->exists ? $page->revision + 1 : 0;
                if ($path) {
                    $old = $page->image_path;
                    $values['image_path'] = $path;
                }
                $page->fill($values)->save();
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('local')->delete($path);
            } throw $e;
        }
        if ($old) {
            Storage::disk('local')->delete($old);
        }

        return redirect()->route('admin.catalogue.index')->with('status', 'Catalogue page saved.');
    }

    private function checkRevision(CataloguePage $page, int $revision): void
    {
        if ($page->revision !== $revision) {
            throw ValidationException::withMessages(['revision' => 'This page changed. Reload before saving or deleting.']);
        }
    }

    public function destroy(Request $request, CataloguePage $page)
    {
        $data = $request->validate(['revision' => 'required|integer|min:0']);
        $path = null;
        DB::transaction(function () use ($page, $data, &$path) {
            $page = CataloguePage::lockForUpdate()->findOrFail($page->id);
            $this->checkRevision($page, (int) $data['revision']);
            $path = $page->image_path;
            $page->delete();
        });
        if ($path) {
            Storage::disk('local')->delete($path);
        }

        return redirect()->route('admin.catalogue.index')->with('status', 'Catalogue page deleted.');
    }
}
