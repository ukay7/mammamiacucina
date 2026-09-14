<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductMediaManager
{
    public function validate(Request $r, ?Product $product): array
    {
        $owned = Rule::exists('product_media', 'id')->where('product_id', $product?->id ?? 0);

        return $r->validate([
            'media_files' => 'nullable|array|max:10',
            'media_files.*' => 'required|file|mimetypes:image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm|max:51200',
            'media_order' => 'nullable|array', 'media_order.*' => 'integer|min:0|max:9999',
            'remove_media' => 'nullable|array', 'remove_media.*' => ['integer', $owned],
        ]);
    }

    public function save(Request $r, Product $product, array &$stored, array &$deleted): void
    {
        $media = $product->media()->lockForUpdate()->get();
        $remove = array_map('intval', $r->input('remove_media', []));
        foreach ($media as $item) {
            if (in_array($item->id, $remove, true)) {
                $deleted[] = $item->path;
                $item->delete();

                continue;
            }
            if ($r->has('media_order.'.$item->id)) {
                $item->update(['sort_order' => $r->input('media_order.'.$item->id)]);
            }
        }
        $next = (int) $product->media()->max('sort_order') + 1;
        foreach ($r->file('media_files', []) as $file) {
            $path = $file->store('product-media/'.$product->id, 'local');
            if (! $path) {
                throw new \RuntimeException('Unable to save product media.');
            }
            $stored[] = $path;
            $product->media()->create(['path' => $path, 'original_name' => mb_substr($file->getClientOriginalName(), 0, 255), 'mime_type' => $file->getMimeType(), 'kind' => str_starts_with($file->getMimeType(), 'video/') ? 'video' : 'image', 'sort_order' => $next++]);
        }
    }
}
