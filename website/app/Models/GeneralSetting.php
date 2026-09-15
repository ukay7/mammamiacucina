<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeneralSetting extends Model
{
    protected $guarded = ['id'];

    public function logoUrl(): string { return $this->logo_path ? route('site.logo') : asset('logo.png'); }

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
        return ['opening_hours' => 'array', 'delivery_cents' => 'integer', 'tax_basis_points' => 'integer', 'revision' => 'integer'];
    }
}
