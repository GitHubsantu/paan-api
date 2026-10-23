<?php

namespace App\Services\Gateway;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GatewayClient
{
    /** @return array{transaction_id: string, checkout_url: string, raw: array<string, mixed>} */
    public function createTransaction(array $body): array
    {
        $response = $this->request('POST', '/api/gateway/v1/transactions', $body);
        $this->logLocalResponse('create_transaction', $response);

        if ($response->status() !== 201 || ! is_array($response->json('data')) || ! is_string($response->json('data.checkout_url'))) {
            throw new GatewayUnavailableException('The gateway did not create a checkout transaction.');
        }

        $data = $response->json('data');
        $transactionId = $data['transaction_id'] ?? $data['id'] ?? null;
        if (! is_string($transactionId) || $transactionId === '') {
            throw new GatewayUnavailableException('The gateway response did not include a transaction id.');
        }

        return ['transaction_id' => $transactionId, 'checkout_url' => $data['checkout_url'], 'raw' => $response->json()];
    }

    /** @return array<string, mixed> */
    public function getTransaction(string $transactionId): array
    {
        $response = $this->request('GET', "/api/gateway/v1/transactions/{$transactionId}", ['domain' => $this->domain()], ['domain' => $this->domain()]);
        $this->logLocalResponse('get_transaction', $response);

        if (! $response->successful() || ! is_array($response->json('data'))) {
            throw new GatewayUnavailableException('The gateway transaction lookup failed.');
        }

        return $response->json('data');
    }

    public function verifyWebhookSignature(string $rawBody, ?string $signature): bool
    {
        return is_string($signature) && hash_equals(hash_hmac('sha256', $rawBody, $this->secret()), $signature);
    }

    public function verifyReturnSignature(?string $transactionId, ?string $status, ?string $merchantReference, ?string $signature): bool
    {
        if (! is_string($transactionId) || ! is_string($status) || ! is_string($signature)) {
            return false;
        }

        $canonical = implode('|', [$transactionId, $status, $merchantReference ?? '']);

        return hash_equals(hash_hmac('sha256', $canonical, $this->secret()), $signature);
    }

    /** @return array<string, string> */
    public function buildHeaders(string $method, string $path, array $body, ?string $timestamp = null, ?string $nonce = null): array
    {
        $timestamp ??= (string) time();
        $nonce ??= bin2hex(random_bytes(16));
        $json = $this->sortedJson($body);
        $canonical = implode("\n", [strtoupper($method), '/'.ltrim($path, '/'), $timestamp, $nonce, $json]);

        return [
            'X-Api-Key' => (string) config('services.gateway.api_key'),
            'X-Timestamp' => $timestamp,
            'X-Nonce' => $nonce,
            'X-Signature' => hash_hmac('sha256', $canonical, $this->secret()),
        ];
    }

    private function request(string $method, string $path, array $body, array $query = []): Response
    {
        $headers = $this->buildHeaders($method, $path, $body);
        $url = rtrim((string) config('services.gateway.base_url'), '/').'/'.ltrim($path, '/');
        if ($query !== []) {
            $url .= '?'.http_build_query($query);
        }

        try {
            return Http::withOptions(['verify' => (bool) config('services.gateway.verify_ssl')])
                ->connectTimeout(3)->timeout(10)->withHeaders($headers)
                ->withBody($this->sortedJson($body), 'application/json')->send($method, $url);
        } catch (ConnectionException $exception) {
            throw new GatewayUnavailableException('The gateway connection failed.', previous: $exception);
        }
    }

    private function sortedJson(array $body): string
    {
        ksort($body);

        return $body === [] ? '{}' : (string) json_encode($body, JSON_UNESCAPED_SLASHES);
    }

    private function domain(): string
    {
        return (string) config('services.gateway.domain');
    }

    private function secret(): string
    {
        return (string) config('services.gateway.api_secret');
    }

    private function logLocalResponse(string $operation, Response $response): void
    {
        if (app()->environment('local')) {
            Log::info('gateway.response', ['operation' => $operation, 'status' => $response->status(), 'body' => $response->json()]);
        }
    }
}
