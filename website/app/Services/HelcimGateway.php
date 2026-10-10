<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class HelcimGateway
{
    public function __construct(private GatewayConfiguration $configuration) {}

    private function client(string $mode): PendingRequest
    {
        $token = $this->configuration->credentials('helcim', $mode)['api_token'] ?? null;
        if (! $token || ! in_array($mode, ['sandbox', 'live'], true)) {
            throw new RuntimeException('Helcim credentials are unavailable.');
        }

        // The token selects the merchant/test account; there is no sandbox URL or test flag.
        return Http::baseUrl('https://api.helcim.com/v2')->withHeaders(['api-token' => $token])
            ->asJson()->acceptJson()->connectTimeout(8)->timeout(25);
    }

    private function json($response): array
    {
        if (! $response->successful() || ! is_array($response->json())) {
            // Provider responses may include sensitive information. Never log or flash them.
            throw new RuntimeException('Helcim could not confirm the request.');
        }
        return $response->json();
    }

    public function testConnection(string $mode): void
    {
        $data = $this->json($this->client($mode)->get('/connection-test'));
        if (($data['message'] ?? '') !== 'Connected Successfully') {
            throw new RuntimeException('Helcim authentication failed.');
        }
        // Authentication alone is insufficient: reconciliation also requires transaction read access.
        $transactions = $this->json($this->client($mode)->get('/card-transactions', ['limit' => 1]));
        if (! array_is_list($transactions)) {
            throw new RuntimeException('Helcim transaction access is unavailable.');
        }
    }

    public function invoiceNumber(Payment $payment): string
    {
        return 'MMC'.str_replace('-', '', $payment->reference);
    }

    public function create(Payment $payment): array
    {
        $order = $payment->order;
        $lines = $order->items->map(fn ($item) => [
            'sku' => $item->qr_code ?: ($item->product_code ?: ''), 'description' => $item->name,
            'quantity' => $item->quantity, 'price' => $item->unit_cents / 100, 'total' => $item->line_cents / 100,
        ])->all();
        if ($order->delivery_cents) {
            $lines[] = ['description' => 'Delivery', 'quantity' => 1, 'price' => $order->delivery_cents / 100, 'total' => $order->delivery_cents / 100];
        }
        $data = $this->json($this->client($payment->mode)->post('/helcim-pay/initialize', [
            'paymentType' => 'purchase', 'paymentMethod' => 'cc', 'amount' => $payment->amount_cents / 100,
            'currency' => 'CAD', 'allowPartial' => 0, 'hasConvenienceFee' => 0, 'confirmationScreen' => false,
            'hideExistingPaymentDetails' => 1, 'setAsDefaultPaymentMethod' => 0, 'language' => 'en',
            'invoiceRequest' => ['invoiceNumber' => $this->invoiceNumber($payment),
                'contactName' => trim($order->first_name.' '.$order->last_name),
                'notes' => 'Mamma Mia Cucina order '.$order->number,
                'lineItems' => $lines, 'tax' => ['amount' => $order->tax_cents / 100, 'details' => 'Order tax']],
        ]));
        if (! is_string($data['checkoutToken'] ?? null) || ! preg_match('/^[a-zA-Z0-9_-]{10,255}$/D', $data['checkoutToken'])) {
            throw new RuntimeException('Helcim checkout could not be initialized.');
        }
        // We independently retrieve transactions from the API; callback data and secretToken are not stored.
        return ['checkoutToken' => $data['checkoutToken']];
    }

    public function transactions(Payment $payment): array
    {
        $all = [];
        for ($page = 1; $page <= 10; $page++) {
            $rows = $this->json($this->client($payment->mode)->get('/card-transactions', [
                'invoiceNumber' => $this->invoiceNumber($payment), 'limit' => 100, 'page' => $page,
            ]));
            if (! array_is_list($rows)) {
                throw new RuntimeException('Unexpected Helcim transaction list.');
            }
            foreach ($rows as $row) {
                if (($row['invoiceNumber'] ?? '') !== $this->invoiceNumber($payment)) {
                    throw new RuntimeException('Helcim invoice mismatch.');
                }
                $all[(string) ($row['transactionId'] ?? '')] = $row;
            }
            if (count($rows) < 100) return array_values($all);
        }
        throw new RuntimeException('Helcim transaction list requires manual review.');
    }

    public function transaction(string $mode, string $id): array
    {
        if (! preg_match('/^[0-9]{1,20}$/D', $id)) throw new RuntimeException('Invalid transaction ID.');
        $data = $this->json($this->client($mode)->get('/card-transactions/'.$id));
        if ((string) ($data['transactionId'] ?? '') !== $id) throw new RuntimeException('Transaction mismatch.');
        return $data;
    }

    public function refund(Payment $payment, ?int $refundCents = null): array
    {
        $amount = $refundCents ?? ($payment->amount_cents - $payment->refunded_cents);
        // Same order/refund request always uses the same key, including after a timeout or DB rollback.
        $data = $this->json($this->client($payment->mode)->withHeaders(['idempotency-key' => substr(hash('sha256', $payment->reference.':'.$payment->refunded_cents.':'.$amount), 0, 32)])
            ->post('/payment/refund', ['originalTransactionId' => (int) $payment->transaction_id,
                'amount' => $amount / 100, 'ipAddress' => request()->ip(), 'ecommerce' => true]));
        if (($data['status'] ?? '') !== 'APPROVED' || ($data['type'] ?? '') !== 'refund' ||
            ($data['currency'] ?? '') !== 'CAD' || app(PaymentGateway::class)->cents((string) ($data['amount'] ?? '')) !== $amount ||
            ! preg_match('/^[0-9]{1,20}$/D', (string) ($data['transactionId'] ?? ''))) {
            throw new RuntimeException('Helcim refund is not confirmed.');
        }
        return ['id' => (string) $data['transactionId'], 'status' => 'completed', 'amount' => $amount];
    }

    public function verifyWebhook(string $mode, string $body, string $id, string $timestamp, string $signature): array
    {
        if (! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > 300 || ! preg_match('/^[a-zA-Z0-9_-]{1,200}$/D', $id)) {
            throw new RuntimeException('Invalid webhook timestamp or ID.');
        }
        $secret = base64_decode($this->configuration->credentials('helcim', $mode)['webhook_secret'] ?? '', true);
        if (! $secret) throw new RuntimeException('Webhook verifier is unavailable.');
        $expected = base64_encode(hash_hmac('sha256', $id.'.'.$timestamp.'.'.$body, $secret, true));
        $valid = false;
        foreach (preg_split('/\s+/', trim($signature)) as $part) {
            if (str_starts_with($part, 'v1,') && hash_equals($expected, substr($part, 3))) $valid = true;
        }
        if (! $valid) throw new RuntimeException('Invalid webhook signature.');
        return json_decode($body, true, 32, JSON_THROW_ON_ERROR);
    }
}
