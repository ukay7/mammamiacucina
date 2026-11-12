<?php

namespace Tests\Feature;

use App\Models\{GeneralSetting, Order, OrderSettlement};
use App\Services\{CustomerInvoicing, DocumentTheme};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DocumentDeliveryDetailsTest extends TestCase
{
    use RefreshDatabase;

    protected function order(array $extra = []): Order
    {
        $order = Order::create($extra + [
            'checkout_token' => Str::uuid(), 'number' => 'MMC-ROUTE-QA', 'source' => 'website',
            'first_name' => 'Delivery', 'last_name' => 'Customer', 'email' => 'delivery@example.test', 'phone' => '123',
            'address' => '10 Original Warehouse', 'city' => 'Toronto', 'province' => 'ON', 'postal_code' => 'M1P 1A1', 'country' => 'Canada',
            'fulfillment' => 'delivery', 'delivery_service' => 'bullet', 'delivery_service_name' => 'Bullet',
            'delivery_from_postal' => 'M2N', 'delivery_to_postal' => 'M1P 1A1', 'delivery_from_zone' => 8, 'delivery_to_zone' => 3,
            'delivery_rate_cents' => 5876, 'subtotal_cents' => 1000, 'delivery_cents' => 5876, 'tax_cents' => 50, 'currency' => 'CAD',
            'payment_method' => 'cash', 'payment_status' => 'unpaid', 'status' => 'completed', 'ordered_at' => now(),
        ]);
        $order->items()->create(['name' => 'Sample cake', 'product_code' => 'ROUTE-1', 'qr_code' => 'ROUTE-100', 'quantity' => 1, 'unit_cents' => 1000, 'line_cents' => 1000]);

        return $order->fresh();
    }

    public function test_invoice_keeps_issued_delivery_details_after_order_and_settings_change(): void
    {
        $order = $this->order();
        $invoice = app(CustomerInvoicing::class)->issue($order);
        $savedInvoice = $invoice->fresh()->getRawOriginal();
        $order->updateQuietly(['address' => '20 Replacement Warehouse', 'postal_code' => 'L6R 1A1',
            'delivery_service' => 'direct', 'delivery_service_name' => 'Direct', 'delivery_from_postal' => 'M4B',
            'delivery_to_postal' => 'L6R 1A1', 'delivery_from_zone' => 2, 'delivery_to_zone' => 9,
            'delivery_rate_cents' => 4000, 'delivery_cents' => 4000]);
        GeneralSetting::findOrFail(1)->update(['warehouse_postal_code' => 'M5V']);
        $theme = app(DocumentTheme::class);
        $doc = $theme->order($order->fresh(), $invoice->fresh());
        $html = view('documents.financial', ['doc' => $doc, 'pdf' => true])->render();
        foreach (['10 Original Warehouse', 'Courier: Bullet', 'From: M2N (Zone 8)', 'To: M1P 1A1 (Zone 3)', 'Saved route rate: CAD 58.76'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        foreach (['20 Replacement Warehouse', 'Direct', 'L6R 1A1', 'M4B', '40.00'] as $text) {
            $this->assertStringNotContainsString($text, $doc['delivery']);
        }
        $this->assertSame('$69.26', $doc['totals']['Total CAD']);
        $this->assertSame($savedInvoice, $invoice->fresh()->getRawOriginal());

        $receipt = OrderSettlement::create(['order_id' => $order->id, 'method' => 'cash', 'amount_cents' => 1000, 'note' => 'Payment for amended order', 'created_at' => now()]);
        foreach ([$theme->order($order->fresh()), $theme->order($order->fresh(), receipt: $receipt)] as $current) {
            $this->assertStringContainsString('20 Replacement Warehouse', $current['delivery']);
            $this->assertStringContainsString('Courier: Direct', $current['delivery']);
            $this->assertStringContainsString('Saved route rate: CAD 40.00', $current['delivery']);
            $this->assertStringNotContainsString('M2N', $current['delivery']);
        }
    }

    public function test_pickup_invoice_keeps_its_saved_pickup_address_and_hides_courier_details(): void
    {
        $order = $this->order(['fulfillment' => 'pickup', 'pickup_address' => 'Original pickup counter', 'delivery_cents' => 0]);
        $invoice = app(CustomerInvoicing::class)->issue($order);
        $order->updateQuietly(['pickup_address' => 'Replacement pickup counter']);
        GeneralSetting::findOrFail(1)->update(['pickup_address' => 'Current company counter']);
        $theme = app(DocumentTheme::class);
        $doc = $theme->order($order->fresh(), $invoice);
        $this->assertSame('Pick up: Original pickup counter', $doc['delivery']);
        $this->assertSame('$0.00', $doc['totals']['Delivery']);
        $this->assertSame('Pick up: Replacement pickup counter', $theme->order($order->fresh())['delivery']);
    }

    public function test_older_invoice_does_not_borrow_missing_route_details_from_current_order(): void
    {
        $order = $this->order();
        $invoice = app(CustomerInvoicing::class)->issue($order);
        $snapshot = $invoice->snapshot;
        foreach (['pickup_address', 'delivery_service', 'delivery_service_name', 'delivery_from_postal', 'delivery_to_postal', 'delivery_from_zone', 'delivery_to_zone', 'delivery_rate_cents'] as $field) {
            unset($snapshot['order'][$field]);
        }
        $invoice->update(['snapshot' => $snapshot]);
        $before = $invoice->fresh()->getRawOriginal();
        $doc = app(DocumentTheme::class)->order($order, $invoice->fresh());
        $this->assertSame('Delivery: 10 Original Warehouse, Toronto, ON, M1P 1A1, Canada', $doc['delivery']);
        $this->assertSame($before, $invoice->fresh()->getRawOriginal());
    }
}
