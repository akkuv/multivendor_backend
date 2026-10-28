<?php

namespace App\Services\Payment;

use App\Models\PaymentSetting;
use Illuminate\Support\Facades\Http;

class RazorpayService
{
    protected string $keyId;

    protected string $keySecret;

    protected string $baseUrl;

    protected string $webhookSecret;

    public function __construct(?PaymentSetting $settings = null)
    {
        $settings ??= PaymentSetting::query()->first();

        $this->keyId = $settings?->razorpay_key_id ?: (string) config('services.razorpay.key_id', '');
        $this->keySecret = $settings?->hasRazorpayKeySecret()
            ? (string) $settings->razorpay_key_secret
            : (string) config('services.razorpay.key_secret', '');
        $this->baseUrl = config('services.razorpay.base_url', 'https://api.razorpay.com/v1');
        $this->webhookSecret = $settings?->hasRazorpayWebhookSecret()
            ? (string) $settings->razorpay_webhook_secret
            : (string) config('services.razorpay.webhook_secret', '');
    }

    public function keyId(): string
    {
        return $this->keyId;
    }

    /**
     * Clone the service with ad-hoc credentials so an admin can test keys that
     * have not been persisted yet. Blank overrides fall back to current values.
     */
    public function withCredentials(?string $keyId = null, ?string $keySecret = null): self
    {
        $clone = clone $this;

        if (filled($keyId)) {
            $clone->keyId = $keyId;
        }

        if (filled($keySecret)) {
            $clone->keySecret = $keySecret;
        }

        return $clone;
    }

    public function hasCredentials(): bool
    {
        return $this->keyId !== '' && $this->keySecret !== '';
    }

    public function checkConnection(): bool
    {
        $response = Http::withBasicAuth($this->keyId, $this->keySecret)
            ->timeout(10)
            ->get("{$this->baseUrl}/balance");

        return $response->successful();
    }

    public function createOrder(array $data): array
    {
        $response = Http::withBasicAuth($this->keyId, $this->keySecret)
            ->post("{$this->baseUrl}/orders", [
                'amount' => (int) round($data['amount'] * 100),
                'currency' => $data['currency'] ?? 'INR',
                'receipt' => $data['receipt'] ?? null,
                'notes' => $data['notes'] ?? [],
            ]);

        if (! $response->successful()) {
            logger()->error('Razorpay create order failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \Exception('Unable to create payment order.');
        }

        return $response->json();
    }

    public function capturePayment(string $paymentId, float $amount): array
    {
        $response = Http::withBasicAuth($this->keyId, $this->keySecret)
            ->post("{$this->baseUrl}/payments/{$paymentId}/capture", [
                'amount' => (int) round($amount * 100),
                'currency' => 'INR',
            ]);

        if (! $response->successful()) {
            throw new \Exception('Unable to capture payment.');
        }

        return $response->json();
    }

    public function verifyPayment(string $orderId, string $paymentId, string $signature): bool
    {
        $expected = hash_hmac(
            'sha256',
            "{$orderId}|{$paymentId}",
            $this->keySecret,
        );

        return hash_equals($expected, $signature);
    }

    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        if (! $this->webhookSecret) {
            return false;
        }

        $expected = base64_encode(hash_hmac('sha256', $payload, $this->webhookSecret, true));

        return hash_equals($expected, $signature);
    }

    public function fetchPayment(string $paymentId): array
    {
        $response = Http::withBasicAuth($this->keyId, $this->keySecret)
            ->get("{$this->baseUrl}/payments/{$paymentId}");

        if (! $response->successful()) {
            logger()->error('Razorpay fetch payment failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \Exception('Unable to fetch payment details.');
        }

        return $response->json();
    }

    public function refundPayment(string $paymentId, float $amount, array $notes = []): array
    {
        $response = Http::withBasicAuth($this->keyId, $this->keySecret)
            ->post("{$this->baseUrl}/payments/{$paymentId}/refund", [
                'amount' => (int) round($amount * 100),
                'notes' => $notes,
            ]);

        if (! $response->successful()) {
            logger()->error('Razorpay refund failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \Exception('Unable to process refund.');
        }

        return $response->json();
    }
}
