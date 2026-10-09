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

    public function pageBanner()
    {
        $settings=GeneralSetting::findOrFail(1);
        abort_unless($settings->page_banner_path && Storage::disk('local')->exists($settings->page_banner_path),404);
        return response()->file(Storage::disk('local')->path($settings->page_banner_path),['X-Content-Type-Options'=>'nosniff','Cache-Control'=>'no-cache']);
    }

    public function edit()
    {
        return view('admin.settings.general', ['settings' => GeneralSetting::findOrFail(1)]);
    }

    public function update(Request $r)
    {
        $d = $r->validate(['pickup_address'=>'sometimes|nullable|string|max:2000','page_banner'=>'nullable|file|image|mimes:png,jpg,jpeg,webp|max:5120','warehouse_postal_code'=>'sometimes|nullable|string|max:7','matrix_delivery_enabled'=>'sometimes|boolean','facebook_url'=>'sometimes|nullable|url:http,https|max:500','instagram_url'=>'sometimes|nullable|url:http,https|max:500','twitter_url'=>'sometimes|nullable|url:http,https|max:500','logo'=>'nullable|file|image|mimes:png,jpg,jpeg,webp|max:5120','email'=>'sometimes|nullable|email|max:255','phone'=>['sometimes','nullable','string','max:60','regex:/^[0-9+(). x-]+$/i'],'youtube'=>'sometimes|nullable|string|max:500','opening_hours'=>'sometimes|array:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday','opening_hours.*.open'=>'required|date_format:H:i','opening_hours.*.close'=>'required|date_format:H:i','opening_hours.*.closed'=>'required|boolean','revision' => 'required|integer|min:0', 'delivery' => ['required', 'regex:/^\d{1,7}(\.\d{1,2})?$/'], 'tax' => ['required','numeric','min:0','max:100','regex:/^\d{1,3}(\.\d{1,2})?$/']]);
        if (!empty($d['youtube']) && !GeneralSetting::youtubeId($d['youtube'])) throw ValidationException::withMessages(['youtube'=>'Enter a valid YouTube video URL or 11-character video ID.']);
        if(!empty($d['warehouse_postal_code']))$d['warehouse_postal_code']=\App\Services\DeliveryQuote::postal($d['warehouse_postal_code']);
        $path=null;$old=null;$bannerPath=null;$oldBanner=null;
        try {
        if ($r->hasFile('logo')) {
            $path=$r->file('logo')->store('branding','local');
            if (!$path) throw new \RuntimeException('Unable to save logo.');
        }
        if ($r->hasFile('page_banner')) {
            $bannerPath=$r->file('page_banner')->store('branding','local');
            if (!$bannerPath) throw new \RuntimeException('Unable to save banner.');
        }
        DB::transaction(function () use ($d, $path, &$old, $bannerPath, &$oldBanner) {
            $settings = GeneralSetting::lockForUpdate()->findOrFail(1);
            $enabled = $d['matrix_delivery_enabled'] ?? $settings->matrix_delivery_enabled;
            $origin = array_key_exists('warehouse_postal_code', $d) ? $d['warehouse_postal_code'] : $settings->warehouse_postal_code;
            if ($enabled) {
                if (!$origin || !DB::table('delivery_postal_zones')->where('prefix', substr($origin, 0, 3))->exists()) {
                    throw ValidationException::withMessages(['warehouse_postal_code'=>'Choose a warehouse postal code in the imported delivery coverage area.']);
                }
                foreach (['bullet','direct','rush','same_day','overnight'] as $code) {
                    if (DB::table('delivery_rates')->where('service_code',$code)->whereBetween('from_zone',[1,30])->whereBetween('to_zone',[1,30])->count() !== 900) {
                        throw ValidationException::withMessages(['matrix_delivery_enabled'=>'Import all five complete rate matrices before enabling.']);
                    }
                }
            }

            if ($settings->revision !== (int) $d['revision']) {
                throw ValidationException::withMessages(['settings' => 'Settings have changed. Reload before saving.']);
            }$cents = static function ($v) {
                $parts = explode('.', $v);

                return (int) $parts[0] * 100 + (int) str_pad($parts[1] ?? '', 2, '0');
            };
            $extra=array_intersect_key($d,array_flip(['pickup_address','warehouse_postal_code','matrix_delivery_enabled','email','phone','opening_hours','facebook_url','instagram_url','twitter_url']));
            if (array_key_exists('youtube',$d)) $extra['youtube_video_id']=GeneralSetting::youtubeId($d['youtube']);
            if ($bannerPath) {$oldBanner=$settings->page_banner_path;$extra['page_banner_path']=$bannerPath;}
            if ($path) {$old=$settings->logo_path;$extra['logo_path']=$path;}
            $settings->update($extra + ['delivery_cents' => $cents($d['delivery']), 'tax_basis_points' => $cents($d['tax']), 'revision' => $settings->revision + 1]);
        });

        } catch (\Throwable $e) {if($path) Storage::disk('local')->delete($path);if($bannerPath) Storage::disk('local')->delete($bannerPath);throw $e;}
        if($oldBanner) Storage::disk('local')->delete($oldBanner);
        if($old) Storage::disk('local')->delete($old);

        return redirect()->route('admin.settings.general')->with('status', 'General settings saved. Website details are updated; new orders use the saved charges.');
    }
}
