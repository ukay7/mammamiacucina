<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BannerAdminTest extends TestCase
{
    use RefreshDatabase;

    private function actor(string $role = 'Super Admin'): User
    {
        return User::factory()->create(['role_id' => Role::where('name', $role)->value('id'), 'is_active' => true]);
    }

    private function data(array $extra = []): array
    {
        return [...['heading' => "Fresh Cakes\nMade to Share", 'subheading' => 'Baked with love', 'button_text' => 'Shop Products', 'alt_text' => 'Cake on a plate', 'priority' => 1, 'is_active' => 1], ...$extra];
    }

    private function image(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('banner.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j0ZkAAAAASUVORK5CYII='));
    }

    public function test_existing_banners_are_preserved_and_all_buttons_use_grid(): void
    {
        $this->assertDatabaseCount('banners', 4);
        $response = $this->get('/')->assertOk()->assertSee('Authentic Italian')->assertSee('Homemade with love')->assertSee('Golden Layers,');
        $hero = explode('</section>', explode('<section class="mmc-hero', $response->getContent())[1])[0];
        $this->assertSame(4, substr_count($hero, 'class="mmc-menu-cta" href="'.route('theme.product-grid').'"'));
        foreach (Banner::all() as $banner) {
            $this->assertFileExists(public_path($banner->asset_path));
        }
    }

    public function test_upload_edit_replace_and_order_banners(): void
    {
        Storage::fake('local');
        $this->actingAs($this->actor());
        $this->get('/admin/banners')->assertOk()->assertSee('2129')->assertSee('739');
        $this->get('/admin/banners/create')->assertOk();
        $this->post('/admin/banners', [...$this->data(), 'image' => $this->image()])->assertSessionHasNoErrors()->assertRedirect('/admin/banners');
        $banner = Banner::latest('id')->first();
        $first = $banner->image_path;
        Storage::disk('local')->assertExists($first);
        $this->get('/admin/banners/'.$banner->id.'/edit')->assertOk();
        $this->put('/admin/banners/'.$banner->id, $this->data(['priority' => 20, 'heading' => 'Updated Banner']))->assertSessionHasNoErrors();
        $this->assertSame($first, $banner->fresh()->image_path);
        $this->get('/')->assertSeeInOrder(['Authentic Italian', 'Golden Layers,', 'Updated Banner']);
        $this->put('/admin/banners/'.$banner->id, [...$this->data(['is_active' => 0]), 'image' => $this->image()])->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($first);
        Storage::disk('local')->assertExists($banner->fresh()->image_path);
        $this->get('/')->assertDontSee('Fresh Cakes');
        $this->get(route('banner.image', $banner))->assertOk();
        auth()->logout();
        $this->get(route('banner.image', $banner))->assertNotFound();
        $banner->refresh()->update(['is_active' => true]);
        $this->get(route('banner.image', $banner))->assertOk()->assertHeader('Content-Type', 'image/png');
    }

    public function test_validation_permissions_and_safe_heading_rendering(): void
    {
        $this->get('/admin/banners')->assertRedirect('/admin/login');
        $this->actingAs($this->actor('Staff'));
        $this->get('/admin/banners')->assertForbidden();
        $this->post('/admin/banners', $this->data())->assertForbidden();
        $this->actingAs($this->actor());
        $this->post('/admin/banners', $this->data())->assertSessionHasErrors('image');
        $this->post('/admin/banners', [...$this->data(['priority' => -1]), 'image' => UploadedFile::fake()->create('bad.php', 1, 'text/plain')])->assertSessionHasErrors(['priority', 'image']);
        $banner = Banner::first();
        $banner->update(['heading' => '<script>alert(1)</script>']);
        $this->get('/')->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_one_or_no_active_banners_preserve_header_without_rotation_controls(): void
    {
        Banner::where('id', '>', 1)->update(['is_active' => false]);
        $this->get('/')->assertOk()->assertSee('Authentic Italian')->assertDontSee('class="mmc-slider-arrow', false);
        Banner::query()->update(['is_active' => false]);
        $this->get('/')->assertOk()->assertSee('mmc-hero--empty')->assertSee('Contact Us')->assertDontSee('id="mmc-hero-title"',false);
    }
}
