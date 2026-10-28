<?php

namespace App\Services\Shipping;

use App\Exceptions\ShiprocketApiException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ShiprocketService
{
    protected const TOKEN_CACHE_KEY = 'shiprocket_api_token';

    public function __construct(protected ShiprocketSettingsService $settings) {}

    public function baseUrl(): string
    {
        return $this->settings->baseUrl();
    }

    /**
     * Resolve a bearer token from stored credentials. Config tokens are used
     * directly; otherwise we authenticate against Shiprocket and cache the
     * token for a short window.
     */
    public function authenticate(bool $force = false): string
    {
        $credentials = $this->settings->credentials();

        if (filled($credentials['token'])) {
            return (string) $credentials['token'];
        }

        $token = $force ? null : Cache::get(self::TOKEN_CACHE_KEY);

        if (is_string($token)) {
            return $token;
        }

        if (blank($credentials['email']) || blank($credentials['password'])) {
            throw new ShiprocketApiException(__('Shiprocket credentials are not configured.'), 'auth/login', null, retryable: false);
        }

        $response = Http::baseUrl($this->baseUrl())
            ->asJson()
            ->post('/auth/login', [
                'email' => $credentials['email'],
                'password' => $credentials['password'],
            ]);

        if (! $response->successful() || blank($response->json('token'))) {
            throw new ShiprocketApiException(__('Shiprocket authentication failed.'), 'auth/login', $this->errorBody($response), retryable: false);
        }

        $token = (string) $response->json('token');
        Cache::put(self::TOKEN_CACHE_KEY, $token, now()->addMinutes(50));

        return $token;
    }

    public function createOrder(array $payload): array
    {
        return $this->request('post', '/shipments/create/adhoc', $payload);
    }

    /** @param array{shipment_id: int|string, courier_id?: int|string} $payload */
    public function assignAwb(array $payload): array
    {
        return $this->request('post', '/courier/assign/awb', $payload);
    }

    /** @param array{shipment_id: int|string, courier_id?: int|string} $payload */
    public function generateAwb(array $payload): array
    {
        return $this->request('post', '/courier/generate/awb', $payload);
    }

    public function serviceabilityCheck(array $payload): array
    {
        return $this->request('post', '/courier/serviceability/check', $payload);
    }

    /**
     * Every courier Shiprocket can assign, with its serviceability regions.
     * Used to seed the courier reference list in the admin UI.
     */
    public function allCouriers(): array
    {
        return $this->request('get', '/courierlist');
    }

    /**
     * Pickup locations registered on the connected Shiprocket account.
     *
     * @return array<int, array<string, mixed>>
     */
    public function pickupLocations(): array
    {
        $response = $this->request('get', '/pickup/list');

        return array_values(array_filter(
            (array) ($response['data'] ?? $response['pickup_locations'] ?? []),
            'is_array',
        ));
    }

    /**
     * Request a pickup for one or more shipment ids.
     *
     * @param  array{shipment_id: int|string}|array<int, array<string, mixed>>  $payload
     */
    public function requestPickup(array $payload): array
    {
        $body = isset($payload['shipment_id']) ? [$payload] : $payload;

        return $this->request('post', '/shipments/pickup', $body);
    }

    public function generateLabel(int $shipmentId): array
    {
        return $this->request('get', '/shipments/print/label', ['shipment_id' => [$shipmentId]]);
    }

    /** @param  array<int, int|string>  $ids */
    public function generateManifest(array $ids): array
    {
        return $this->request('get', '/shipments/print/manifest', ['shipment_ids' => array_values($ids)]);
    }

    public function trackByAwb(string $awb): array
    {
        return $this->request('get', "/courier/track/awb/{$awb}");
    }

    public function trackByOrder(string $shiprocketOrderId): array
    {
        return $this->request('get', '/courier/track', ['order_id' => $shiprocketOrderId]);
    }

    /** @param array<int, int|string> $ids */
    public function cancel(array $ids): array
    {
        return $this->request('post', '/shipments/cancel', ['ids' => array_values($ids)]);
    }

    /**
     * Verify an inbound webhook against the configured HMAC-SHA256 secret.
     *
     * This fails CLOSED: if no secret is configured, the webhook is rejected
     * rather than accepted unverified. Configuring a webhook secret is
     * mandatory before inbound tracking updates can be processed.
     */
    public function verifyWebhookSignature(string $rawPayload, string $signature): bool
    {
        $secret = $this->settings->webhookSecret();

        if (blank($secret)) {
            return false;
        }

        $expected = hash_hmac('sha256', $rawPayload, (string) $secret);

        return hash_equals($expected, strtolower(trim($signature)));
    }

    protected function request(string $method, string $uri, array $payload = []): array
    {
        $options = $method === 'get'
            ? ['query' => $payload]
            : ['json' => $payload];

        $attempts = 3;

        $send = fn (): Response => Http::retry($attempts, 200, throw: false)
            ->baseUrl($this->baseUrl())
            ->withHeaders($this->authHeaders())
            ->send($method, $this->url($uri), $options);

        $response = $send();

        if ($response->status() === 401) {
            $this->refreshToken();
            $response = $send();
        }

        if (! $response->successful()) {
            throw new ShiprocketApiException(
                "Shiprocket {$method} {$uri} failed with status {$response->status()}.",
                $uri,
                $this->errorBody($response),
            );
        }

        return (array) ($response->json() ?? []);
    }

    /** @return array<string, string> */
    protected function authHeaders(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->authenticate(),
            'Content-Type' => 'application/json',
        ];
    }

    protected function refreshToken(): void
    {
        Cache::forget(self::TOKEN_CACHE_KEY);
    }

    protected function url(string $uri): string
    {
        return ltrim($uri, '/');
    }

    /** @return array<string, mixed> */
    protected function errorBody(Response $response): array
    {
        $body = $response->json();

        return is_array($body) ? $body : ['body' => $response->body()];
    }
}
