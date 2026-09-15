<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GeneralSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class GeneralSettingController extends Controller
{
    public function logo()
    {
        $settings=GeneralSetting::findOrFail(1);
        abort_unless($settings->logo_path && Storage::disk('local')->exists($settings->logo_path),404);
        return response()->file(Storage::disk('local')->path($settings->logo_path),['X-Content-Type-Options'=>'nosniff','Cache-Control'=>'no-cache']);
    }

    public function edit()
    {
        return view('admin.settings.general', ['settings' => GeneralSetting::findOrFail(1)]);
    }

    public function update(Request $r)
    {
        $d = $r->validate(['facebook_url'=>'sometimes|nullable|url:http,https|max:500','instagram_url'=>'sometimes|nullable|url:http,https|max:500','twitter_url'=>'sometimes|nullable|url:http,https|max:500','logo'=>'nullable|file|image|mimes:png,jpg,jpeg,webp|max:5120','email'=>'sometimes|nullable|email|max:255','phone'=>['sometimes','nullable','string','max:60','regex:/^[0-9+(). x-]+$/i'],'youtube'=>'sometimes|nullable|string|max:500','opening_hours'=>'sometimes|array:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday','opening_hours.*.open'=>'required|date_format:H:i','opening_hours.*.close'=>'required|date_format:H:i','opening_hours.*.closed'=>'required|boolean','revision' => 'required|integer|min:0', 'delivery' => ['required', 'regex:/^\d{1,7}(\.\d{1,2})?$/'], 'tax' => ['required','numeric','min:0','max:100','regex:/^\d{1,3}(\.\d{1,2})?$/']]);
        if (!empty($d['youtube']) && !GeneralSetting::youtubeId($d['youtube'])) throw ValidationException::withMessages(['youtube'=>'Enter a valid YouTube video URL or 11-character video ID.']);
        $path=null;$old=null;
        try {
        if ($r->hasFile('logo')) {
            $path=$r->file('logo')->store('branding','local');
            if (!$path) throw new \RuntimeException('Unable to save logo.');
        }
        DB::transaction(function () use ($d, $path, &$old) {
            $settings = GeneralSetting::lockForUpdate()->findOrFail(1);
            if ($settings->revision !== (int) $d['revision']) {
                throw ValidationException::withMessages(['settings' => 'Settings have changed. Reload before saving.']);
            }$cents = static function ($v) {
                $parts = explode('.', $v);

                return (int) $parts[0] * 100 + (int) str_pad($parts[1] ?? '', 2, '0');
            };
            $extra=array_intersect_key($d,array_flip(['email','phone','opening_hours','facebook_url','instagram_url','twitter_url']));
            if (array_key_exists('youtube',$d)) $extra['youtube_video_id']=GeneralSetting::youtubeId($d['youtube']);
            if ($path) {$old=$settings->logo_path;$extra['logo_path']=$path;}
            $settings->update($extra + ['delivery_cents' => $cents($d['delivery']), 'tax_basis_points' => $cents($d['tax']), 'revision' => $settings->revision + 1]);
        });

        } catch (\Throwable $e) {if($path) Storage::disk('local')->delete($path);throw $e;}
        if($old) Storage::disk('local')->delete($old);

        return redirect()->route('admin.settings.general')->with('status', 'General settings saved. Website details are updated; new orders use the saved charges.');
    }
}
