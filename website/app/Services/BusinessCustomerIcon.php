<?php

namespace App\Services;

use App\Models\{Customer, GeneralSetting};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BusinessCustomerIcon
{
    public const DEFAULT_ASSET = 'assets/images/business-customer/building-fill.png';

    public function filePath(): string
    {
        $path = GeneralSetting::find(1)?->business_customer_icon_path;
        return $path && Storage::disk('local')->exists($path)
            ? Storage::disk('local')->path($path) : public_path(self::DEFAULT_ASSET);
    }

    public function prime(iterable $customerIds): void
    {
        $ids = collect($customerIds)->filter()->unique()->reject(fn ($id) => request()->attributes->has('business-customer-type.'.$id));
        foreach ($ids->chunk(500) as $chunk) {
            $types = Customer::whereIn('id', $chunk)->pluck('account_type', 'id');
            foreach ($chunk as $id) request()->attributes->set('business-customer-type.'.$id, ($types[$id] ?? null) === 'business');
        }
    }

    public function isBusiness(mixed $customer = null, ?int $customerId = null): bool
    {
        if ($customer instanceof \App\Models\User) return $customer->account_type === 'business';
        $attributes = $customer instanceof \Illuminate\Database\Eloquent\Model ? $customer->getAttributes() : (array) $customer;
        if (array_key_exists('account_type', $attributes)) return $attributes['account_type'] === 'business';
        // Older document snapshots did not store account_type. Resolve their linked
        // account without rewriting the saved document or guessing from its name.
        $id = $customerId ?? data_get($customer, 'id');
        if (!$id) return false;
        $key = 'business-customer-type.'.$id;
        if (!request()->attributes->has($key)) {
            request()->attributes->set($key, Customer::whereKey($id)->value('account_type') === 'business');
        }
        return request()->attributes->get($key);
    }

    public function source(bool $embedded = false): ?string
    {
        $key = 'business-customer-icon.'.($embedded ? 'embedded' : 'url');
        if (request()->attributes->has($key)) return request()->attributes->get($key);
        $path = $this->filePath();
        $source = null;
        if (is_file($path)) {
            $source = $embedded
                ? 'data:image/png;base64,'.base64_encode(file_get_contents($path))
                : route('site.business-customer-icon', ['v' => substr(sha1($path), 0, 12)]);
        }
        request()->attributes->set($key, $source);
        return $source;
    }

    public function store(UploadedFile $file): string
    {
        if (!extension_loaded('gd')) {
            throw ValidationException::withMessages(['business_customer_icon' => 'PHP GD must be enabled to save the icon.']);
        }
        $source = @imagecreatefromstring($file->get());
        if (!$source) throw ValidationException::withMessages(['business_customer_icon' => 'Choose a valid PNG, JPG or WebP image.']);
        $icon = imagecreatetruecolor(128, 128);
        imagealphablending($icon, false);
        imagesavealpha($icon, true);
        imagefill($icon, 0, 0, imagecolorallocatealpha($icon, 0, 0, 0, 127));
        $scale = min(128 / imagesx($source), 128 / imagesy($source));
        $width = max(1, (int) round(imagesx($source) * $scale));
        $height = max(1, (int) round(imagesy($source) * $scale));
        imagecopyresampled($icon, $source, (int) ((128 - $width) / 2), (int) ((128 - $height) / 2), 0, 0, $width, $height, imagesx($source), imagesy($source));
        ob_start();
        imagepng($icon);
        $bytes = ob_get_clean();
        imagedestroy($source);
        imagedestroy($icon);
        $path = 'branding/business-customers/'.Str::uuid().'.png';
        if (!Storage::disk('local')->put($path, $bytes)) throw new \RuntimeException('Unable to save business customer icon.');
        return $path;
    }
}
