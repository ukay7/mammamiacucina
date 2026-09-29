<?php

namespace Tests\Feature;

use App\Models\GeneralSetting;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContactPageSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function login(bool $allowed = true): void
    {
        $role = $allowed ? Role::where('is_super', true)->firstOrFail() : Role::create(['name' => 'Read only contact', 'permissions' => ['dashboard.view']]);
        $this->actingAs(User::factory()->create(['role_id' => $role->id, 'is_active' => true]));
    }

    private function data(): array
    {
        return ['revision' => 0, 'contact_eyebrow' => 'Say hello', 'contact_heading' => 'Visit our kitchen', 'contact_description' => 'A little Italy.', 'contact_image_alt' => 'Fresh pastries', 'whatsapp_number' => '+1 (416) 555-0123', 'instagram_url' => 'https://instagram.com/test', 'facebook_url' => 'https://facebook.com/test', 'website_url' => 'https://example.com', 'email' => 'hello@example.com', 'phone' => '+1 416 555 9999'];
    }

    private function photo()
    {
        return UploadedFile::fake()->createWithContent('contact.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j0ZkAAAAASUVORK5CYII='));
    }

    public function test_updates_both_surfaces_preserves_other_settings_and_enquiries(): void
    {
        $this->login();
        $before = GeneralSetting::find(1);
        $this->get(route('admin.contact.edit'))->assertOk()->assertSeeInOrder(['<span>About Us</span>', '<span>Contact Us</span>', '<span>General Settings</span>'], false);
        $this->put(route('admin.contact.update'), $this->data())->assertSessionHasNoErrors();
        $s = GeneralSetting::find(1);
        $this->assertSame('+14165550123', $s->whatsapp_number);
        $this->assertSame($before->opening_hours, $s->opening_hours);
        $this->assertSame($before->delivery_cents, $s->delivery_cents);
        foreach (['/', '/contact'] as $url) {
            $this->get($url)->assertOk()->assertSee('https://wa.me/14165550123', false)->assertSee('https://instagram.com/test', false)->assertSee('https://facebook.com/test', false)->assertSee('https://example.com', false)->assertSee('mailto:hello@example.com', false)->assertSee('tel:+14165559999', false);
        }
        $this->get('/contact')->assertSee('Visit our kitchen')->assertSee('Send Us a Message');
        $this->post(route('contact.store'), ['name' => 'Customer', 'email' => 'customer@example.com', 'phone' => '1234567', 'message' => 'Please call me'])->assertSessionHas('contact_success');
        $this->assertDatabaseHas('contact_enquiries', ['email' => 'customer@example.com']);
    }

    public function test_optional_fields_hide_and_content_is_escaped(): void
    {
        $this->login();
        $d = $this->data();
        foreach (['whatsapp_number', 'instagram_url', 'facebook_url', 'website_url', 'email', 'phone'] as $field) {
            $d[$field] = '';
        }
        $d['contact_heading'] = '<script>alert(1)</script>';
        $this->put(route('admin.contact.update'), $d)->assertSessionHasNoErrors();
        $this->get('/contact')->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('https://wa.me/', false)->assertDontSee('mailto:', false);
    }

    public function test_upload_replacement_and_stale_form_cleanup(): void
    {
        Storage::fake('local');
        $this->login();
        $this->put(route('admin.contact.update'), $this->data() + ['image' => $this->photo()])->assertSessionHasNoErrors();
        $old = GeneralSetting::find(1)->contact_image_path;
        $this->get(route('contact.image'))->assertOk();
        $this->put(route('admin.contact.update'), $this->data() + ['image' => $this->photo()])->assertSessionHasErrors('settings');
        $this->assertCount(1, Storage::disk('local')->allFiles('contact'));
        $this->put(route('admin.contact.update'), array_replace($this->data(), ['revision' => 1, 'image' => $this->photo()]))->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($old);
        $this->assertCount(1, Storage::disk('local')->allFiles('contact'));
    }

    public function test_permissions_and_unsafe_links(): void
    {
        $this->get(route('admin.contact.edit'))->assertRedirect(route('admin.login'));
        $this->login(false);
        $this->get(route('admin.contact.edit'))->assertForbidden();
        $this->put(route('admin.contact.update'), $this->data())->assertForbidden();
        $this->login();
        $this->put(route('admin.contact.update'), array_replace($this->data(), ['website_url' => 'javascript:alert(1)', 'instagram_url' => 'javascript:alert(1)', 'facebook_url' => 'javascript:alert(1)', 'whatsapp_number' => '123', 'email' => 'bad']))->assertSessionHasErrors(['website_url', 'instagram_url', 'facebook_url', 'email']);
        $this->put(route('admin.contact.update'), array_replace($this->data(),['whatsapp_number' => '123']))->assertSessionHasErrors('whatsapp_number');
    }
}
