<?php

namespace Tests\Feature;

use App\Models\AboutPage;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AboutPageTest extends TestCase
{
    use RefreshDatabase;

    private function login(bool $allowed = true): void
    {
        $role = $allowed ? Role::where('is_super', true)->firstOrFail() : Role::create(['name' => 'Viewer', 'permissions' => ['dashboard.view']]);
        $this->actingAs(User::factory()->create(['role_id' => $role->id, 'is_active' => true]));
    }

    private function data(): array
    {
        return ['quote_supporting_text' => "Imported with care.\nServed with love.", 'closing_sentence' => 'Made with love for every occasion.', 'revision' => 0, 'eyebrow' => 'Our story', 'heading' => 'Fresh from Italy', 'description' => "First paragraph.\n\nSecond paragraph.", 'image_alt' => 'Our cakes', 'button_name' => 'Browse treats', 'button_page' => 'product-grid', 'items' => array_map(fn ($i) => ['title' => 'Highlight '.$i, 'description' => 'Description '.$i], range(1, 6))];
    }

    private function photo()
    {
        return UploadedFile::fake()->createWithContent('about.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j0ZkAAAAASUVORK5CYII='));
    }

    public function test_existing_content_is_preserved_and_admin_link_is_below_home(): void
    {
        $this->get('/about')->assertOk()->assertSee('A Little Italy,')->assertSee('Italian Favourites')->assertSee('category-cakes-hd.png');
        $this->assertCount(3, AboutPage::find(1)->items);
        $this->login();
        $this->get(route('admin.about.edit'))->assertOk()->assertSeeInOrder(['<span>Home</span>', '<span>About Us</span>', '<span>General Settings</span>'], false);
    }

    public function test_content_image_and_reordered_items_save_and_render(): void
    {
        Storage::fake('local');
        $this->login();
        $data = $this->data();
        $data['items'] = array_reverse($data['items']);
        $this->put(route('admin.about.update'), $data + ['image' => $this->photo()])->assertSessionHasNoErrors();
        $about = AboutPage::find(1);
        Storage::disk('local')->assertExists($about->image_path);
        $this->get(route('about.image'))->assertOk();
        $this->get('/about')->assertOk()->assertSee('Fresh from Italy')->assertSee('Browse treats')->assertSeeInOrder(['Made with love for every occasion.','Imported with care.','Highlight 6', 'Highlight 5', 'Highlight 1']);
        $this->assertCount(6, $about->items);
        $this->put(route('admin.about.update'), array_replace($data, ['revision' => 1, 'items' => []]))->assertSessionHasNoErrors();
        $this->assertSame($about->image_path, AboutPage::find(1)->image_path);
        $this->get('/about')->assertDontSee('data-about-carousel', false);
    }

    public function test_permissions_validation_and_stale_upload_cleanup(): void
    {
        Storage::fake('local');
        $this->login(false);
        $this->get(route('admin.about.edit'))->assertForbidden();
        $this->put(route('admin.about.update'), $this->data())->assertForbidden();
        $this->login();
        $this->put(route('admin.about.update'), $this->data() + ['image' => $this->photo()])->assertSessionHasNoErrors();
        $this->put(route('admin.about.update'), $this->data() + ['image' => $this->photo()])->assertSessionHasErrors('about');
        $this->assertCount(1, Storage::disk('local')->allFiles('about'));
        $this->put(route('admin.about.update'), array_replace($this->data(), ['revision' => 1, 'button_page' => 'javascript:alert(1)']))->assertSessionHasErrors('button_page');
        $this->put(route('admin.about.update'), array_replace($this->data(), ['revision' => 1, 'items' => [['title' => '', 'description' => 'Missing heading']]]))->assertSessionHasErrors('items.0.title');
        $this->put(route('admin.about.update'), array_replace($this->data(), ['revision' => 1, 'image' => UploadedFile::fake()->create('bad.svg', 1, 'image/svg+xml')]))->assertSessionHasErrors('image');
    }

    public function test_replacement_removes_old_upload_and_text_is_escaped(): void
    {
        Storage::fake('local');
        $this->login();
        $this->put(route('admin.about.update'), $this->data() + ['image' => $this->photo()])->assertSessionHasNoErrors();
        $old = AboutPage::find(1)->image_path;
        $this->put(route('admin.about.update'), array_replace($this->data(), ['revision' => 1, 'heading' => '<script>alert(1)</script>', 'image' => $this->photo()]))->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($old);
        $this->get('/about')->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;',false)->assertDontSee('<script>alert(1)</script>',false);
    }
}
