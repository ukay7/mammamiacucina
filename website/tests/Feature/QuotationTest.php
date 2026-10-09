<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class QuotationTest extends TestCase
{
    use RefreshDatabase;

    private function admin()
    {
        return User::factory()->create(['is_active' => true, 'role_id' => Role::where('is_super', true)->value('id')]);
    }

    public function test_admin_customer_creation_and_business_approval_are_separate(): void
    {
        $this->actingAs($this->admin());
        $this->get(route('admin.customers.create'))->assertOk();
        $d = ['name' => 'New Customer', 'email' => 'NEW@example.test', 'password' => 'safe-password-123', 'password_confirmation' => 'safe-password-123', 'account_type' => 'individual', 'address' => '12 Test Street', 'website' => 'https://example.test'];
        $this->post(route('admin.customers.store'), $d)->assertSessionHasNoErrors()->assertRedirect();
        $u = User::where('email', 'new@example.test')->firstOrFail();
        $this->assertTrue(Hash::check($d['password'], $u->password));
        $this->assertNotNull($u->email_verified_at);
        $this->assertSame('12 Test Street', $u->customer->address);
        $this->assertNull($u->role_id);
        $this->post(route('admin.customers.store'), $d)->assertSessionHasErrors('email');
        $d = array_replace($d, ['email' => 'biz@example.test', 'account_type' => 'business', 'business_bin' => '123', 'business_name' => 'Business', 'business_phone' => '123', 'business_email' => 'biz@example.test']);
        $this->post(route('admin.customers.store'), $d)->assertSessionHasNoErrors();
        $u = User::where('email', 'biz@example.test')->firstOrFail();
        $this->assertNull($u->business_approved_at);
        $this->assertNull($u->email_verified_at);
    }

    public function test_quote_calculations_snapshots_edit_print_and_delete(): void
    {
        $this->actingAs($this->admin());
        $buyer = User::factory()->create(['account_type' => 'business', 'is_active' => true, 'email_verified_at' => now(), 'business_approved_at' => now()]);
        $c = $buyer->customerRecord();
        $p = Product::create(['category_id' => 1, 'slug' => 'quote-cake', 'premium_marketing_name' => 'Quote Cake', 'is_active' => true, 'total_selling_price_cad' => 15, 'business_selling_price_cad' => 12]);
        $d = ['customer_id' => $c->id, 'due_date' => now()->addDays(20)->format('Y-m-d'), 'pricing_tier' => 'auto', 'items' => [['product_id' => $p->id, 'quantity' => 2, 'unit_price' => '20.00', 'discount_percent' => '10.00']], 'discount_percent' => '10.00', 'charge_percent' => '10', 'tax_percent' => '13', 'payment_terms'=>'Net 15 days', 'delivery_terms'=>'Pickup, FOB Vaughan', 'notes' => 'Special offer'];
        $this->get(route('admin.quotations.create'))->assertOk();
        $this->post(route('admin.quotations.store'), $d)->assertSessionHasNoErrors();
        $q = Quotation::firstOrFail();
        $this->assertSame('business', $q->pricing_tier);
        $this->get(route('admin.quotations.print',$q))->assertOk()->assertSee('Net 15 days')->assertSee('Pickup, FOB Vaughan');
        $this->assertEquals(4027, $q->total_cents);
        $this->assertEquals(2000, $q->items[0]['unit_cents']);
        foreach (['index', 'show', 'edit', 'print'] as $route) {
            $this->get(route('admin.quotations.'.$route, $route === 'index' ? [] : $q))->assertOk();
        }
        $p->update(['business_selling_price_cad' => 99]);
        $this->put(route('admin.quotations.update', $q), $d + ['revision' => 1])->assertSessionHasNoErrors();
        $this->assertEquals(4027, $q->fresh()->total_cents);
        $this->put(route('admin.quotations.update', $q), $d + ['revision' => 1])->assertSessionHasErrors('revision');
        $this->post(route('admin.quotations.store'), array_replace($d, ['discount_percent' => 101]))->assertSessionHasErrors('discount_percent');
        $this->post(route('admin.quotations.store'), array_replace($d, ['items' => []]))->assertSessionHasErrors('items');
        $bad = $d;
        $bad['items'][0]['unit_price'] = '-1';
        $this->post(route('admin.quotations.store'), $bad)->assertSessionHasErrors('items.0.unit_price');
        $bad = $d;
        $bad['items'][0]['discount_percent'] = 101;
        $this->post(route('admin.quotations.store'), $bad)->assertSessionHasErrors('items.0.discount_percent');
        $this->assertEquals(1000, $q->fresh()->discount_basis_points);
        $this->assertEquals(1000, $q->fresh()->items[0]['discount_basis_points']);
        $this->assertEquals(15, (float) $p->fresh()->total_selling_price_cad);
        $this->assertDatabaseCount('orders', 0);
        $this->delete(route('admin.quotations.destroy', $q), ['revision' => 1])->assertStatus(409);
        $this->delete(route('admin.quotations.destroy', $q), ['revision' => 2])->assertRedirect();
        $this->assertDatabaseCount('quotations', 0);
    }

    public function test_permissions_and_pending_business_default_to_individual(): void
    {
        $staff = User::factory()->create(['is_active' => true, 'role_id' => Role::create(['name' => 'Restricted', 'permissions' => []])->id]);
        $this->actingAs($staff)->get(route('admin.quotations.index'))->assertForbidden();
        $this->post(route('admin.customers.store'), [])->assertForbidden();
        $this->actingAs($this->admin());
        $buyer = User::factory()->create(['account_type' => 'business', 'business_approved_at' => null]);
        $p = Product::create(['category_id' => 1, 'slug' => 'pending-cake', 'premium_marketing_name' => 'Pending Cake', 'is_active' => true, 'total_selling_price_cad' => 15, 'business_selling_price_cad' => 12]);
        $d = ['customer_id' => $buyer->customerRecord()->id, 'due_date' => now()->format('Y-m-d'), 'pricing_tier' => 'auto', 'items' => [['product_id' => $p->id, 'quantity' => 1, 'unit_price' => '15.00', 'discount_percent' => 0]], 'discount_percent' => 0, 'charge_percent' => 0, 'tax_percent' => 0];
        $this->post(route('admin.quotations.store'), $d)->assertSessionHasNoErrors();
        $this->assertSame('individual', Quotation::first()->pricing_tier);
        $this->assertEquals(1500, Quotation::first()->total_cents);
    }

    public function test_category_snapshots_linked_prices_precision_and_read_only_product_details(): void
    {
        $this->actingAs($this->admin());
        $buyer = User::factory()->create(['account_type' => 'individual']);
        $category = Category::create(['name' => 'Dessert category', 'slug' => 'dessert-category']);
        $p = Product::create(['category_id' => $category->id, 'slug' => 'six-piece-cake', 'premium_marketing_name' => 'Six piece cake', 'qr_code' => '0010020', 'is_active' => true, 'total_selling_price_cad' => 25, 'dna' => ['pieces_per_carton' => 6]]);
        $form = $this->get(route('admin.quotations.create'))->assertOk()->assertSee('Choose columns')->assertSee('read-only');
        $this->assertEquals(6, $form->viewData('products')->firstWhere('id', $p->id)['pieces_per_carton']);
        $d = ['customer_id' => $buyer->customerRecord()->id, 'due_date' => now()->addDays(14)->format('Y-m-d'), 'pricing_tier' => 'individual', 'items' => [['product_id' => $p->id, 'quantity' => 3, 'unit_price' => '999', 'sale_unit_price' => '3.672', 'price_basis' => 'sale_unit', 'discount_percent' => 10, 'total_cents' => 1, 'columns' => ['supplier' => 'Forged'], 'pieces_per_carton' => 100]], 'discount_percent' => 0, 'charge_percent' => 0, 'tax_percent' => 0];
        $this->post(route('admin.quotations.store'), $d)->assertSessionHasNoErrors();
        $q = Quotation::firstOrFail();
        $item = $q->items[0];
        $this->assertSame('22.0320', $item['unit_price']);
        $this->assertSame('3.67200000', $item['sale_unit_price']);
        $this->assertSame(6, $item['pieces_per_carton']);
        $this->assertSame('Dessert category', $item['category']);
        $this->assertSame('0010020', $item['code']);
        $this->assertSame(6610, $q->subtotal_cents);
        $this->assertSame(5949, $q->total_cents);
        $this->get(route('admin.quotations.print',$q))->assertOk()->assertSee('PRICE QUOTATION')->assertSee('SUGGESTED RETAIL')->assertSee('$19.8288')->assertSee('$3.3048')->assertSee('$23.7946');
        $this->assertNotSame('Forged', $item['columns']['supplier']);
        $p->update(['dna' => ['pieces_per_carton' => 12], 'premium_marketing_name' => 'Changed name']);
        $category->update(['name' => 'Changed category']);
        $d['items'][0] = ['product_id' => $p->id, 'quantity' => 2, 'unit_price' => '30.1234', 'sale_unit_price' => '999', 'price_basis' => 'carton', 'discount_percent' => 0];
        $this->put(route('admin.quotations.update', $q), $d + ['revision' => 1])->assertSessionHasNoErrors();
        $q->refresh();
        $this->assertSame('30.1234', $q->items[0]['unit_price']);
        $this->assertSame('5.02056667', $q->items[0]['sale_unit_price']);
        $this->assertSame(6025, $q->total_cents);
        $this->assertSame('Dessert category', $q->items[0]['category']);
        $this->get(route('admin.quotations.print', $q))->assertOk()->assertSee('Dessert category')->assertSee('Six piece cake')->assertSee('30.1234')->assertSee('Quote / Sale Unit');
        $this->assertEquals(25, (float) $p->fresh()->total_selling_price_cad);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_unknown_pack_and_invalid_precision_are_rejected_without_changing_old_quotes(): void
    {
        $this->actingAs($this->admin());
        $buyer = User::factory()->create(['account_type' => 'individual']);
        $p = Product::create(['category_id' => 1, 'slug' => 'unknown-pack', 'premium_marketing_name' => 'Unknown pack', 'is_active' => true, 'total_selling_price_cad' => 10]);
        $d = ['customer_id' => $buyer->customerRecord()->id, 'due_date' => now()->format('Y-m-d'), 'pricing_tier' => 'individual', 'items' => [['product_id' => $p->id, 'quantity' => 1, 'unit_price' => '10', 'sale_unit_price' => '2', 'price_basis' => 'sale_unit', 'discount_percent' => 0]], 'discount_percent' => 0, 'charge_percent' => 0, 'tax_percent' => 0];
        $this->post(route('admin.quotations.store'), $d)->assertSessionHasErrors('items');
        $d['items'][0]['price_basis'] = 'carton';
        $d['items'][0]['unit_price'] = '10.12345';
        $this->post(route('admin.quotations.store'), $d)->assertSessionHasErrors('items.0.unit_price');
        $d['items'][0]['unit_price'] = '10.0000';
        $this->post(route('admin.quotations.store'), $d)->assertSessionHasNoErrors();
        $q = Quotation::firstOrFail();
        $this->assertNull($q->items[0]['sale_unit_price']);
        $items = $q->items;
        foreach (['unit_price', 'sale_unit_price', 'price_basis', 'category', 'pieces_per_carton', 'columns'] as $key) {
            unset($items[0][$key]);
        }$q->update(['items' => $items]);
        $this->get(route('admin.quotations.print',$q))->assertOk()->assertSee('10.00');
        $this->get(route('admin.quotations.edit',$q))->assertOk();
    }
}
