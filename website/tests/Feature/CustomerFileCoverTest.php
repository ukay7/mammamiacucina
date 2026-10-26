<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerNote;
use App\Models\Role;
use App\Models\User;
use App\Services\StaffPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class CustomerFileCoverTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): Customer
    {
        $customer = User::factory()->create(['name' => 'Contact Person', 'email' => 'contact@example.test',
            'phone' => '1234567', 'account_type' => 'individual',
            'role_id' => Role::where('name', 'Customer')->value('id'), 'is_active' => true])->customerRecord();
        $customer->update(['business_name' => 'Test Bakery', 'business_phone' => '7654321',
            'business_email' => 'bakery@example.test', 'address' => '123 Example Street',
            'city' => 'Toronto', 'province' => 'ON', 'postal_code' => 'M5V 1A1', 'country' => 'Canada']);

        return $customer->fresh();
    }

    private function viewer(): User
    {
        $role = Role::create(['name' => 'Customer cover viewer',
            'permissions' => StaffPermissions::normalize(['access.admin.customers.index'])]);

        return User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    }

    public function test_year_and_volume_form_is_above_notes_and_is_available_to_customer_viewers(): void
    {
        $customer = $this->customer();
        $html = $this->actingAs($this->viewer())->get(route('admin.customers.show', $customer))->assertOk()->getContent();
        $this->assertLessThan(strpos($html, 'id="customer-notes"'), strpos($html, 'id="customer-file-cover"'));
        $this->assertStringContainsString('action="'.route('admin.customers.file-cover', $customer).'"', $html);
        $this->assertStringContainsString('name="year"', $html);
        $this->assertStringContainsString('name="volume"', $html);
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringNotContainsString('id="customer-note-modal"', $html);
    }

    public function test_inline_pdf_is_one_a4_page_and_runtime_values_never_write_to_customer_data(): void
    {
        $customer = $this->customer();
        $original = $customer->getAttributes();
        $originalUser = $customer->user->getAttributes();
        $captured = [];
        View::composer('documents.customer-file-cover', function ($view) use (&$captured) {
            $captured[] = $view->getData()['cover'];
        });
        $this->actingAs($this->viewer());
        $writes = [];
        DB::listen(function ($query) use (&$writes) {
            if (preg_match('/^\s*(insert|update|delete|replace)\b/i', $query->sql)) {
                $writes[] = $query->sql;
            }
        });
        foreach (['01', '02'] as $volume) {
            $response = $this->get(route('admin.customers.file-cover', [$customer, 'year' => '2026', 'volume' => $volume]))
                ->assertOk()->assertHeader('Content-Type', 'application/pdf')
                ->assertHeader('Content-Disposition', 'inline; filename="customer-file-'.$customer->id.'.pdf"');
            $pdf = $response->getContent();
            $this->assertStringStartsWith('%PDF-', $pdf);
            $this->assertSame(1, preg_match_all('/\/Type\s*\/Page\b/', $pdf));
            $this->assertSame(1, preg_match('/\/MediaBox\s*\[([\d.\s]+)\]/', $pdf, $matches));
            $box = preg_split('/\s+/', trim($matches[1]));
            $this->assertEqualsWithDelta(595.28, (float) $box[2], 0.1);
            $this->assertEqualsWithDelta(841.89, (float) $box[3], 0.1);
        }
        $this->assertSame(['01', '02'], array_column($captured, 'volume'));
        $this->assertSame(['2026', '2026'], array_column($captured, 'year'));
        $this->assertSame('TEST BAKERY', $captured[0]['name']);
        $this->assertSame((string) $customer->id, $captured[0]['id']);
        $this->assertSame('Contact Person', $captured[0]['contact']);
        $this->assertSame('7654321', $captured[0]['phone']);
        $this->assertSame('bakery@example.test', $captured[0]['email']);
        $this->assertSame('123 Example Street, Toronto, ON, M5V 1A1, Canada', $captured[0]['address']);
        $this->assertStringStartsWith('data:image/', $captured[0]['brand']['logo']);
        $this->assertSame([], $writes);
        $this->assertSame($original, $customer->fresh()->getAttributes());
        $this->assertSame($originalUser, $customer->user->fresh()->getAttributes());
        $this->assertSame(0, CustomerNote::count());
    }

    public function test_individual_customer_uses_personal_details_and_cover_text_is_escaped(): void
    {
        $customer = $this->customer();
        $customer->update(['business_name' => null, 'business_phone' => null, 'business_email' => null, 'address' => '<b>Example & Test</b>']);
        $cover = null;
        View::composer('documents.customer-file-cover', function ($view) use (&$cover) {
            $cover = $view->getData()['cover'];
        });
        $this->actingAs($this->viewer())->get(route('admin.customers.file-cover', [$customer, 'year' => '2026/27', 'volume' => '003']))->assertOk();
        $this->assertSame('CONTACT PERSON', $cover['name']);
        $this->assertSame('1234567', $cover['phone']);
        $this->assertSame('contact@example.test', $cover['email']);
        $this->assertSame('003', $cover['volume']);
        $html = view('documents.customer-file-cover', compact('cover'))->render();
        $this->assertStringContainsString('&lt;b&gt;Example &amp; Test&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('<b>Example & Test</b>', $html);
        $this->assertStringContainsString('Volume: [003]', $html);
        $this->assertStringContainsString('Year: [2026/27]', $html);
    }

    public function test_cover_requires_customer_view_access_and_valid_bounded_runtime_inputs(): void
    {
        $customer = $this->customer();
        $url = route('admin.customers.file-cover', [$customer, 'year' => '2026', 'volume' => '01']);
        $this->get($url)->assertRedirect(route('admin.login'));
        $this->actingAs($customer->user)->get($url)->assertRedirect(route('customer.orders'));
        $unauthorized = User::factory()->create(['is_active' => true,
            'role_id' => Role::create(['name' => 'Unrelated module', 'permissions' => StaffPermissions::normalize(['access.admin.products.index'])])->id]);
        $this->actingAs($unauthorized)->get($url)->assertForbidden();
        $this->actingAs($this->viewer());
        $this->getJson(route('admin.customers.file-cover', $customer))->assertUnprocessable()->assertJsonValidationErrors(['year', 'volume']);
        $this->getJson(route('admin.customers.file-cover', [$customer, 'year' => str_repeat('2', 21), 'volume' => ['01']]))
            ->assertUnprocessable()->assertJsonValidationErrors(['year', 'volume']);
        $this->get(route('admin.customers.file-cover', [999999, 'year' => '2026', 'volume' => '01']))->assertNotFound();
    }
}
