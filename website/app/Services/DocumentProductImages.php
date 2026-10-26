<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductMedia;
use Illuminate\Support\Facades\Storage;

class DocumentProductImages
{
    public function forItems(array $items): array
    {
        $lines = collect($items);
        $ids = $lines->pluck('product_id')->filter()->unique()->values();
        // Older invoice snapshots identify products by their saved internal code.
        $legacy = $lines->filter(fn ($item) => empty($item['product_id']));
        $codes = $legacy->pluck('qr_code')->filter()->unique()->values();
        $supplierCodes = $legacy->filter(fn ($item) => empty($item['qr_code']))->pluck('product_code')->filter()->unique()->values();
        if ($ids->isEmpty() && $codes->isEmpty() && $supplierCodes->isEmpty()) {
            return array_fill_keys(array_keys($items), null);
        }

        $products = Product::where(function ($query) use ($ids, $codes, $supplierCodes) {
            $query->whereIn('id', $ids)->orWhereIn('qr_code', $codes)->orWhereIn('product_code', $supplierCodes);
        })->with(['media' => fn ($query) => $query->coverImage()])->get(['id', 'qr_code', 'product_code']);
        $byId = $products->keyBy('id');
        $byCode = $products->keyBy('qr_code');
        $bySupplier = $products->groupBy('product_code');
        $images = [];

        return $lines->map(function ($item) use ($byId, $byCode, $bySupplier, &$images) {
            if (! empty($item['product_id'])) {
                $product = $byId->get($item['product_id']);
            } elseif (! empty($item['qr_code'])) {
                $product = $byCode->get($item['qr_code']);
            } else {
                $matches = $bySupplier->get($item['product_code'] ?? '');
                // Supplier codes need not be unique; do not guess the picture.
                $product = $matches?->count() === 1 ? $matches->first() : null;
            }
            $media = $product?->media->first();
            if (! $media) {
                return null;
            }
            if (! array_key_exists($media->id, $images)) {
                $images[$media->id] = $this->thumbnail($media);
            }

            return $images[$media->id];
        })->all();
    }

    private function thumbnail(ProductMedia $media): ?string
    {
        $disk = Storage::disk('local');
        $root = realpath($disk->path(''));
        $path = realpath($disk->path($media->path));
        if (! $root || ! $path || ! str_starts_with($path, $root.DIRECTORY_SEPARATOR) || ! is_readable($path)) {
            return null;
        }
        $directory = storage_path('framework/cache/document-products');
        $cache = $directory.'/'.sha1('v1|'.$path.'|'.filemtime($path).'|'.filesize($path)).'.jpg';
        if (is_file($cache) && ($bytes = @file_get_contents($cache))) {
            return 'data:image/jpeg;base64,'.base64_encode($bytes);
        }
        $size = @getimagesize($path);
        if (! extension_loaded('gd') || ! $size || $size[0] * $size[1] > 25000000 ||
            ! in_array($size[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true)) {
            return null;
        }

        $limit = ini_get('memory_limit');
        $source = $small = null;
        try {
            ini_set('memory_limit', '512M');
            $source = @imagecreatefromstring(file_get_contents($path));
            if (! $source) {
                return null;
            }
            // A small, square JPEG works in browser print and remote-disabled PDFs.
            // Contain the full photo on white, including transparent product cutouts.
            $small = imagecreatetruecolor(160, 160);
            imagefill($small, 0, 0, imagecolorallocate($small, 255, 255, 255));
            $scale = min(1, 160 / $size[0], 160 / $size[1]);
            $width = max(1, (int) round($size[0] * $scale));
            $height = max(1, (int) round($size[1] * $scale));
            imagecopyresampled($small, $source, (int) ((160 - $width) / 2), (int) ((160 - $height) / 2), 0, 0, $width, $height, $size[0], $size[1]);
            ob_start();
            imagejpeg($small, null, 85);
            $bytes = ob_get_clean();
        } finally {
            if ($source) {
                imagedestroy($source);
            }
            if ($small) {
                imagedestroy($small);
            }
            ini_set('memory_limit', $limit);
        }
        if (! is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }
        if (is_dir($directory) && is_writable($directory) && ($temporary = @tempnam($directory, 'thumb-'))) {
            if (@file_put_contents($temporary, $bytes) !== false) {
                @rename($temporary, $cache);
            }
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }

        return 'data:image/jpeg;base64,'.base64_encode($bytes);
    }
}
