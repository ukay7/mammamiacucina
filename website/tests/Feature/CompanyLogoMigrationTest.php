<?php

namespace Tests\Feature;

use App\Models\GeneralSetting;
use App\Services\DocumentTheme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompanyLogoMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_deployment_selects_bundled_emblem_without_changing_company_details_or_deleting_uploads(): void
    {
        Storage::fake('local');
        $old = UploadedFile::fake()->image('wordmark.png', 400, 80)->store('branding', 'local');
        $settings = GeneralSetting::findOrFail(1);
        $settings->update(['logo_path' => $old, 'email' => 'company@example.test', 'phone' => '+1 555 555 0101',
            'pickup_address' => 'Company address', 'revision' => 7]);
        $previous = $settings->fresh()->getAttributes();
        $migration = require database_path('migrations/2026_10_27_120000_use_emblem_for_company_logo.php');
        $migration->up();

        $settings->refresh();
        $this->assertNull($settings->logo_path);
        $this->assertSame(8, $settings->revision);
        foreach (array_diff(array_keys($previous), ['logo_path', 'revision', 'updated_at']) as $field) {
            $this->assertSame($previous[$field], $settings->getRawOriginal($field), $field.' changed');
        }
        Storage::disk('local')->assertExists($old);
        $this->assertSame(asset('logo.png'), $settings->logoUrl());
        $this->get(route('site.logo'))->assertOk()->assertHeader('Content-Type', 'image/png');
        $brand = app(DocumentTheme::class)->brand();
        $this->assertStringStartsWith('data:image/jpeg;base64,', $brand['logo']);
        $this->assertSame('company@example.test', $brand['email']);
        $this->assertSame('Company address', $brand['address']);

        $migration->up();
        $this->assertSame(8, $settings->fresh()->revision);
        $replacement = UploadedFile::fake()->image('future.png')->store('branding', 'local');
        $settings->update(['logo_path' => $replacement]);
        $migration->down();
        $this->assertSame($replacement, $settings->fresh()->logo_path);
        Storage::disk('local')->assertExists($replacement);
    }

    public function test_missing_uploaded_file_uses_the_emblem_in_documents_and_the_existing_logo_url(): void
    {
        Storage::fake('local');
        GeneralSetting::findOrFail(1)->update(['logo_path' => 'branding/missing.png']);
        $this->get(route('site.logo'))->assertOk()->assertHeader('Content-Type', 'image/png');
        $missing = app(DocumentTheme::class)->brand()['logo'];
        GeneralSetting::findOrFail(1)->update(['logo_path' => null]);
        $this->assertNotNull($missing);
        $this->assertSame(app(DocumentTheme::class)->brand()['logo'], $missing);
    }
}
