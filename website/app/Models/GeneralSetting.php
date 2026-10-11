<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeneralSetting extends Model
{
    protected $guarded = ['id'];

    public static function defaultContactIcon(array $link): string
    {
        $host = strtolower(parse_url($link['url'] ?? '', PHP_URL_HOST) ?? '');
        $label = strtolower(trim($link['label'] ?? ''));
        foreach (['whatsapp' => ['wa.me', 'whatsapp.com'], 'instagram' => ['instagram.com'], 'facebook' => ['facebook.com', 'fb.com']] as $icon => $domains) {
            if ($label === $icon) return asset('assets/images/social/'.$icon.'.svg');
            foreach ($domains as $domain) {
                if ($host === $domain || str_ends_with($host, '.'.$domain)) return asset('assets/images/social/'.$icon.'.svg');
            }
        }
        return asset('assets/images/social/website.svg');
    }

    public function contactLinks(bool $activeOnly = false): array
    {
        $links = $this->contact_links;
        if ($links === null) {
            $links = [];
            foreach (['whatsapp_number' => 'WhatsApp', 'instagram_url' => 'Instagram', 'facebook_url' => 'Facebook', 'website_url' => 'Website', 'twitter_url' => 'Twitter / X'] as $field => $label) {
                if (!$this->$field) continue;
                $links[] = ['id' => $field, 'label' => $label, 'url' => $field === 'whatsapp_number' ? 'https://wa.me/'.preg_replace('/\D/', '', $this->$field) : $this->$field, 'active' => true, 'sort' => count($links), 'icon' => null];
            }
        }
        return collect($links)->filter(fn ($link) => !$activeOnly || $link['active'])->sortBy('sort')->values()->all();
    }

    public function logoUrl(): string { return $this->logo_path ? route('site.logo') : asset('logo.png'); }

    public function pageBannerUrl(): string { return $this->page_banner_path ? route('site.page-banner') : asset('assets/images/mmc/italian-pastries-hero.png'); }

    public static function youtubeId(?string $value): ?string
    {
        $value=trim($value ?? '');
        if ($value==='') return null;
        if (preg_match('/^[A-Za-z0-9_-]{11}$/',$value)) return $value;
        $url=parse_url($value);$host=strtolower($url['host'] ?? '');$id=null;
        if (!in_array($url['scheme'] ?? '', ['https','http'])) return null;
        if ($host==='youtu.be') $id=trim($url['path'] ?? '', '/');
        if (in_array($host,['youtube.com','www.youtube.com','m.youtube.com','youtube-nocookie.com','www.youtube-nocookie.com'])) {
            parse_str($url['query'] ?? '',$query);$id=$query['v'] ?? null;
            if (!$id && preg_match('~^/(?:embed|shorts|live)/([A-Za-z0-9_-]{11})/?$~',$url['path'] ?? '',$match)) $id=$match[1];
        }
        return is_string($id) && preg_match('/^[A-Za-z0-9_-]{11}$/',$id) ? $id : null;
    }

    public function taxFor(int $subtotalCents):int
    {
        return intdiv($subtotalCents * $this->tax_basis_points + 5000, 10000);
    }

    protected function casts(): array
    {
        return ['contact_links' => 'array', 'show_email' => 'boolean', 'show_phone' => 'boolean', 'opening_hours' => 'array', 'delivery_cents' => 'integer', 'tax_basis_points' => 'integer', 'revision' => 'integer'];
    }
}
