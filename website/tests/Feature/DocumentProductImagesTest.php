<?php

namespace Tests\Feature;

use App\Models\EmailHistory;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\CustomerEmails;
use App\Services\CustomerInvoicing;
use App\Services\DocumentProductImages;
use App\Services\DocumentTheme;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class DocumentProductImagesTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $code = '100035'): Product
    {
        return Product::create(['category_id' => 1, 'slug' => Str::uuid(), 'qr_code' => $code,
            'product_code' => 'SUP-'.$code, 'premium_marketing_name' => 'Current product name', 'is_active' => false]);
    }

    private function photo(Product $product, string $name = 'cake.png', int $sort = 0): string
    {
        $file = UploadedFile::fake()->image($name, 640, 320);
        $path = $file->store('product-media/'.$product->id, 'local');
        $product->media()->create(['kind' => 'image', 'mime_type' => 'image/png', 'path' => $path,
            'original_name' => $name, 'sort_order' => $sort]);

        return $path;
    }

    private function order(Product $product): Order
    {
        $order = Order::create(['checkout_token' => Str::uuid(), 'number' => 'mmc-'.Str::random(8), 'source' => 'website',
            'first_name' => 'Document', 'last_name' => 'Customer', 'email' => 'document@example.test', 'phone' => '123',
            'address' => 'Street', 'city' => 'Toronto', 'province' => 'ON', 'postal_code' => 'M5V', 'country' => 'Canada',
            'subtotal_cents' => 10000, 'delivery_cents' => 0, 'tax_cents' => 0, 'currency' => 'CAD',
            'fulfillment' => 'pickup', 'pickup_address' => 'Warehouse', 'payment_method' => 'cash',
            'payment_status' => 'unpaid', 'status' => 'completed']);
        $order->items()->create(['product_id' => $product->id, 'qr_code' => $product->qr_code, 'product_code' => $product->product_code,
            'name' => 'Saved order product name', 'quantity' => 2, 'unit_cents' => 5000, 'line_cents' => 10000]);

        return $order->fresh();
    }

    public function test_order_print_and_remote_disabled_pdf_embed_the_cover_photo_with_saved_codes_and_amounts(): void
    {
        Storage::fake('local');
        $product = $this->product();
        $product->media()->create(['kind' => 'video', 'path' => 'video.mp4', 'mime_type' => 'video/mp4', 'original_name' => 'Video', 'sort_order' => 0]);
        $this->photo($product, sort: 1);
        $order = $this->order($product);
        $doc = app(DocumentTheme::class)->order($order);
        $this->assertStringStartsWith('data:image/jpeg;base64,', $doc['items'][0]['image']);
        $bytes = base64_decode(explode(',', $doc['items'][0]['image'], 2)[1]);
        $size = getimagesizefromstring($bytes);
        $this->assertSame([160, 160, IMAGETYPE_JPEG], array_slice($size, 0, 3));
        $this->assertLessThan(20000, strlen($bytes));
        $this->assertSame('100035', $doc['items'][0]['code']);
        $this->assertSame('Saved order product name', $doc['items'][0]['name']);
        $this->assertSame('$50.00', $doc['items'][0]['unit']);
        $this->assertSame('$100.00', $doc['items'][0]['total']);
        $this->actingAs(User::factory()->create(['role_id' => Role::where('is_super', true)->value('id'), 'is_active' => true]));
        $this->get(route('admin.orders.print', $order))->assertOk()->assertSee('Photo')->assertSee('doc-product-image')
            ->assertSee($doc['items'][0]['image'], false)->assertSee('Saved order product name');
        $html = view('documents.financial', ['doc' => $doc, 'pdf' => true])->render();
        $pdf = new Dompdf(new Options(['isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans']));
        $pdf->loadHtml($html);
        $pdf->setPaper('A4');
        $pdf->render();
        $output = $pdf->output();
        $this->assertStringStartsWith('%PDF-', $output);
        $this->assertStringContainsString('/Width 160', $output);
        $this->assertStringContainsString('/Height 160', $output);
    }

    public function test_old_invoice_snapshots_get_photos_without_changing_their_saved_details(): void
    {
        Storage::fake('local');
        $product = $this->product();
        $this->photo($product);
        $order = $this->order($product);
        $invoice = app(CustomerInvoicing::class)->issue($order);
        $before = $invoice->fresh()->getAttributes();
        $this->assertArrayNotHasKey('product_id', $invoice->snapshot['items'][0]);
        $product->update(['premium_marketing_name' => 'Renamed product', 'product_code' => 'New supplier code']);
        $order->items()->update(['name' => 'Changed order line', 'unit_cents' => 9999, 'line_cents' => 19998]);
        $doc = app(DocumentTheme::class)->order($order->fresh(), $invoice);
        $this->assertNotNull($doc['items'][0]['image']);
        $this->assertSame('Saved order product name', $doc['items'][0]['name']);
        $this->assertSame('$100.00', $doc['items'][0]['total']);
        $this->assertSame($before, $invoice->fresh()->getAttributes());
    }

    public function test_missing_or_invalid_photos_and_ambiguous_supplier_codes_do_not_break_documents(): void
    {
        Storage::fake('local');
        $first = $this->product();
        $second = $this->product('100036');
        $second->update(['product_code' => $first->product_code]);
        Storage::disk('local')->put('product-media/broken.png', 'not an image');
        $first->media()->create(['kind' => 'image', 'mime_type' => 'image/png', 'path' => 'product-media/broken.png', 'original_name' => 'Broken']);
        $images = app(DocumentProductImages::class)->forItems([
            ['product_id' => $first->id], ['product_id' => $second->id],
            ['product_code' => $first->product_code], ['qr_code' => 'NO-LONGER-EXISTS', 'product_code' => $first->product_code], [],
        ]);
        $this->assertSame([null, null, null, null, null], $images);
        $doc = app(DocumentTheme::class)->order($this->order($first));
        $html = view('documents.financial', compact('doc'))->render();
        $this->assertStringContainsString('No image', $html);
        $this->assertStringContainsString('Saved order product name', $html);
        $this->assertStringNotContainsString('<img class="doc-product-image"', $html);
        $this->photo($second);
        $this->assertNotNull(app(DocumentProductImages::class)->forItems([['product_code' => $second->product_code, 'qr_code' => $second->qr_code]])[0]);
    }

    public function test_queued_email_freezes_the_embedded_photo_and_receipts_share_the_same_layout(): void
    {
        Storage::fake('local');
        $product = $this->product();
        $path = $this->photo($product);
        $order = $this->order($product);
        app(CustomerEmails::class)->order($order, 'order_confirmation', 'order-created:'.$order->id);
        $entry = EmailHistory::firstOrFail();
        $document = $entry->payload['document'];
        $image = $document['doc']['items'][0]['image'];
        Storage::disk('local')->delete($path);
        $this->assertSame($image, $entry->fresh()->payload['document']['doc']['items'][0]['image']);
        $this->assertStringContainsString($image, view('emails.order-document', $document)->render());
        $this->assertNull(app(DocumentTheme::class)->order($order)['items'][0]['image']);
        $product->media()->update(['sort_order' => 1]);
        $this->photo($product, 'replacement.png');
        $receipt = $order->settlements()->create(['method' => 'cash', 'amount_cents' => 2500, 'note' => 'Actual collection']);
        $doc = app(DocumentTheme::class)->order($order->fresh(), null, $receipt);
        $this->assertSame('Payment Receipt', $doc['title']);
        $this->assertNotNull($doc['items'][0]['image']);
        $this->assertStringContainsString('doc-product-image', view('documents.financial', compact('doc'))->render());
    }
}
