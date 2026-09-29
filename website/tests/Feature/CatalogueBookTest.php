<?php

namespace Tests\Feature;

use App\Models\CataloguePage;
use App\Models\Category;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogueBookTest extends TestCase
{
    use RefreshDatabase;

    private function login(bool $allowed = true): void
    {
        $role = $allowed ? Role::where('is_super', true)->firstOrFail() : Role::create(['name' => 'Catalogue viewer', 'permissions' => ['dashboard.view']]);
        $this->actingAs(User::factory()->create(['role_id' => $role->id, 'is_active' => true]));
    }

    private function data(): array
    {
        return ['title' => 'Cover', 'category_id' => '', 'custom_label' => 'Our story', 'sort_order' => 5, 'is_active' => 1, 'revision' => 0];
    }

    private function photo()
    {
        return UploadedFile::fake()->createWithContent('page.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j0ZkAAAAASUVORK5CYII='));
    }

    public function test_sample_is_ready_and_public_navigation_is_ordered(): void
    {
        $this->assertDatabaseCount('catalogue_pages', 36);
        $this->get('/catalogue')->assertOk()->assertSeeInOrder(['Products <span', '>Catalogue</a>', '>Gallery</a>'], false);
        $this->get('/catalogue/reader')->assertOk()->assertViewHas('catalogue', fn ($c) => $c['count'] === 36 && count($c['pages']) === 36);
        $this->get(route('catalogue.image', CataloguePage::first()))->assertOk();
        $this->login();
        $this->get(route('admin.catalogue.index'))->assertOk();
        $this->get(route('admin.catalogue.create'))->assertOk();
    }

    public function test_upload_edit_stale_protection_and_delete_preserve_categories(): void
    {
        Storage::fake('local');
        $this->login();
        $count = Category::count();
        $this->post(route('admin.catalogue.store'), $this->data() + ['image' => $this->photo()])->assertSessionHasNoErrors();
        $page = CataloguePage::latest('id')->first();
        $old = $page->image_path;
        Storage::disk('local')->assertExists($old);
        $this->get(route('admin.catalogue.edit', $page))->assertOk();
        $this->put(route('admin.catalogue.update', $page), array_replace($this->data(), ['image' => $this->photo(), 'is_active' => 0]))->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($old);
        $this->get(route('catalogue.image', $page))->assertNotFound();
        $this->get(route('admin.catalogue.preview', $page))->assertOk();
        $this->put(route('admin.catalogue.update', $page), $this->data() + ['image' => $this->photo()])->assertSessionHasErrors('revision');
        $this->assertCount(1, Storage::disk('local')->allFiles('catalogue-pages'));
        $this->delete(route('admin.catalogue.destroy', $page), ['revision' => 0])->assertSessionHasErrors('revision');
        $this->delete(route('admin.catalogue.destroy', $page), ['revision' => 1])->assertSessionHasNoErrors();
        $this->assertCount(0, Storage::disk('local')->allFiles('catalogue-pages'));
        $this->assertSame($count, Category::count());
    }

    public function test_shared_categories_custom_labels_and_order(): void
    {
        $category = Category::first();
        $pages = CataloguePage::orderBy('id')->take(3)->get();
        CataloguePage::query()->update(['is_active' => false]);
        foreach ($pages as $i => $page) {
            $page->refresh()->update(['is_active' => true, 'sort_order' => $i, 'category_id' => $i !== 1 ? $category->id : null, 'custom_label' => $i !== 1 ? $category->name : 'Special collection']);
        }
        $this->get('/catalogue/reader')->assertViewHas('catalogue', fn ($c) => $c['count'] === 3 && $c['sections'][0]['indices'] === [0, 2] && $c['sections'][1]['name'] === 'Special collection');
        CataloguePage::query()->update(['is_active' => false]);
        $this->get('/catalogue')->assertSee('being prepared')->assertDontSee('<iframe', false);
        $this->get('/catalogue/reader')->assertOk()->assertSee('being prepared');
    }

    public function test_permissions_and_required_image_label(): void
    {
        $this->get(route('admin.catalogue.index'))->assertRedirect(route('admin.login'));
        $this->login(false);
        $this->get(route('admin.catalogue.index'))->assertForbidden();
        $this->post(route('admin.catalogue.store'), $this->data())->assertForbidden();
        $this->login();
        $this->post(route('admin.catalogue.store'), array_replace($this->data(), ['custom_label' => '']))->assertSessionHasErrors(['custom_label', 'image']);
        $this->post(route('admin.catalogue.store'), array_replace($this->data(), ['category_id' => 999999, 'image' => UploadedFile::fake()->create('bad.svg', 1, 'image/svg+xml')]))->assertSessionHasErrors(['category_id', 'image']);
    }

    public function test_bundled_images_bypass_php_and_uploaded_images_have_versioned_cache(): void
    {
        $this->get('/catalogue/reader')->assertViewHas('catalogue', fn ($c) => str_contains($c['pages'][0], '/assets/catalogue/page-01.webp?v=') && str_contains($c['thumbs'][0], '/assets/catalogue/thumb-01.webp?v='));
        Storage::fake('local');
        $this->login();
        $this->post(route('admin.catalogue.store'), $this->data() + ['image' => $this->photo()])->assertSessionHasNoErrors();
        $page = CataloguePage::latest('id')->first();
        $url = route('catalogue.image', [$page, 'v' => 0]);
        $response = $this->get($url)->assertOk();
        $this->assertStringContainsString('max-age=86400', $response->headers->get('Cache-Control'));
        $this->withHeader('If-None-Match', $response->headers->get('ETag'))->get($url)->assertStatus(304);
        $this->flushHeaders();
        $this->get(route('catalogue.image', [$page, 'v' => 99]))->assertHeader('Cache-Control', 'no-cache, private');
        $this->get(route('admin.catalogue.preview', [$page, 'v' => 0]))->assertHeader('Cache-Control', 'no-store, private');
        $page->update(['is_active' => false]);
        $this->get($url)->assertNotFound();
    }
}
