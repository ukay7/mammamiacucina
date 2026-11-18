<?php

namespace Tests\Feature;

use App\Models\{Order, OrderSettlement, Payment};
use App\Services\DocumentTheme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DocumentPaymentReferencesTest extends TestCase
{
    use RefreshDatabase;

    private function order(): Order
    {
        return Order::create(['checkout_token'=>Str::uuid(),'number'=>'MMC-'.Str::uuid(),'source'=>'website',
            'first_name'=>'Document','last_name'=>'Customer','email'=>'document@example.test','phone'=>'123',
            'address'=>'Test street','city'=>'Toronto','province'=>'ON','postal_code'=>'M5V','country'=>'Canada',
            'subtotal_cents'=>1000,'delivery_cents'=>0,'tax_cents'=>0,'currency'=>'CAD','fulfillment'=>'pickup',
            'payment_method'=>'card','payment_status'=>'paid','status'=>'warehouse_pending','ordered_at'=>now()])->fresh();
    }

    private function payment(Order $order, string $provider='helcim'): Payment
    {
        return Payment::create(['order_id'=>$order->id,'reference'=>Str::uuid(),'provider'=>$provider,'mode'=>'sandbox',
            'amount_cents'=>1000,'currency'=>'CAD','status'=>'paid','paid_at'=>now(),'expires_at'=>now()->addHour(),
            'provider_order_id'=>'GATEWAY-INVOICE-123','transaction_id'=>'TRANSACTION-456',
            'checkout_token'=>'private-checkout-token','checkout_url'=>'https://checkout.example.test/private-session'])->fresh();
    }

    public function test_gateway_references_render_without_tokens_or_changes_to_saved_records(): void
    {
        foreach (['helcim'=>'Helcim','stripe'=>'Stripe','paypal'=>'PayPal'] as $provider=>$name) {
            $order=$this->order();$payment=$this->payment($order,$provider);
            $before=[$order->getRawOriginal(),$payment->getRawOriginal()];
            $doc=app(DocumentTheme::class)->order($order);
            $this->assertSame($name.' (test)',$doc['payment_references']['Payment provider']);
            $this->assertSame('TRANSACTION-456',$doc['payment_references']['Transaction reference']);
            $this->assertSame($provider==='helcim',isset($doc['payment_references']['Helcim invoice reference']));
            $html=view('documents.financial',compact('doc'))->render();
            $this->assertStringContainsString('TRANSACTION-456',$html);
            $this->assertStringContainsString('An unpaid or pending order is not proof of payment.',$html);
            $this->assertStringNotContainsString('private-checkout-token',$html);
            $this->assertStringNotContainsString('private-session',$html);
            $this->assertSame($before,[$order->fresh()->getRawOriginal(),$payment->fresh()->getRawOriginal()]);
        }
    }

    public function test_references_are_escaped_and_separate_receipts_keep_their_own_reference(): void
    {
        $order=$this->order();$payment=$this->payment($order);
        $payment->update(['mode'=>'live','provider_order_id'=>'<img src=x>','transaction_id'=>'<script>reference</script>']);
        $doc=app(DocumentTheme::class)->order($order);
        $this->assertSame('Helcim',$doc['payment_references']['Payment provider']);
        $html=view('documents.financial',compact('doc'))->render();
        $this->assertStringContainsString('&lt;script&gt;reference&lt;/script&gt;',$html);
        $this->assertStringContainsString('&lt;img src=x&gt;',$html);
        $this->assertStringNotContainsString('<script>reference</script>',$html);
        $entry=OrderSettlement::create(['order_id'=>$order->id,'method'=>'etransfer','amount_cents'=>100,'reference'=>'SEPARATE-TRANSFER','note'=>'Separate recorded payment','created_at'=>now()]);
        $doc=app(DocumentTheme::class)->order($order->fresh(),receipt:$entry);
        $this->assertSame([],$doc['payment_references']);
        $html=view('documents.financial',compact('doc'))->render();
        $this->assertStringContainsString('SEPARATE-TRANSFER',$html);
        $this->assertStringNotContainsString('Helcim invoice reference:',$html);
        $this->assertStringNotContainsString('Transaction reference:',$html);
    }

    public function test_replaced_gateway_checkouts_are_not_shown_on_the_current_manual_order(): void
    {
        $order=$this->order();$this->payment($order)->update(['superseded_at'=>now()]);
        $order->update(['payment_method'=>'cash','payment_status'=>'unpaid']);
        $doc=app(DocumentTheme::class)->order($order->fresh());
        $this->assertSame([],$doc['payment_references']);
        $html=view('documents.financial',compact('doc'))->render();
        $this->assertStringNotContainsString('TRANSACTION-456',$html);
        $this->assertStringNotContainsString('GATEWAY-INVOICE-123',$html);
    }
}
