<?php

namespace Escalated\Laravel\Services;

use Escalated\Laravel\Models\Webhook;
use Escalated\Laravel\Models\WebhookDelivery;
use Escalated\Laravel\Support\OutboundUrlGuard;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookDispatcher
{
    protected int $maxAttempts = 3;

    /**
     * Dispatch a webhook event to all active subscribers.
     */
    public function dispatch(string $event, array $payload): void
    {
        $webhooks = Webhook::active()->get();

        foreach ($webhooks as $webhook) {
            if ($webhook->subscribedTo($event)) {
                $this->send($webhook, $event, $payload);
            }
        }
    }

    /**
     * Send a webhook delivery with HMAC-SHA256 signing and retry logic.
     */
    public function send(Webhook $webhook, string $event, array $payload, int $attempt = 1): void
    {
        $body = json_encode([
            'event' => $event,
            'payload' => $payload,
            'timestamp' => now()->toIso8601String(),
        ]);

        $headers = [
            'Content-Type' => 'application/json',
            'X-Escalated-Event' => $event,
        ];

        // HMAC-SHA256 signing with secret
        if ($webhook->secret) {
            $signature = hash_hmac('sha256', $body, $webhook->secret);
            $headers['X-Escalated-Signature'] = $signature;
        }

        $delivery = WebhookDelivery::create([
            'webhook_id' => $webhook->id,
            'event' => $event,
            'payload' => $payload,
            'attempts' => $attempt,
        ]);

        // Checked on every attempt, not only when the URL is saved: a row can
        // predate the check, and a host name can be re-pointed at an internal
        // address after it passed.
        $address = app(OutboundUrlGuard::class)->publicAddressFor((string) $webhook->url);

        if ($address === null) {
            $delivery->update([
                'response_code' => 0,
                'response_body' => 'Not sent: the URL must use http or https and resolve to a public address.',
                'attempts' => $attempt,
            ]);

            Log::warning('Escalated webhook not sent: URL does not resolve to a public address', [
                'webhook_id' => $webhook->id,
                'event' => $event,
            ]);

            return;
        }

        try {
            $response = Http::withHeaders($headers)
                ->timeout(10)
                // A redirect would carry the request, and the response body an
                // admin can read back, to an address the check above refused.
                ->withoutRedirecting()
                ->withOptions($this->connectTo($webhook->url, $address))
                ->withBody($body, 'application/json')
                ->post($webhook->url);

            $delivery->update([
                'response_code' => $response->status(),
                'response_body' => substr($response->body(), 0, 2000),
                'delivered_at' => now(),
                'attempts' => $attempt,
            ]);

            // If not successful and more attempts remain, retry with exponential backoff
            if (! $response->successful() && $attempt < $this->maxAttempts) {
                $this->retryLater($webhook, $event, $payload, $attempt + 1);
            }
        } catch (\Throwable $e) {
            $delivery->update([
                'response_code' => 0,
                'response_body' => $e->getMessage(),
                'attempts' => $attempt,
            ]);

            Log::warning('Escalated webhook delivery failed', [
                'webhook_id' => $webhook->id,
                'event' => $event,
                'attempt' => $attempt,
                'error' => $e->getMessage(),
            ]);

            if ($attempt < $this->maxAttempts) {
                $this->retryLater($webhook, $event, $payload, $attempt + 1);
            }
        }
    }

    /**
     * Pins the connection to the address that was checked, so a DNS answer
     * that changes between the check and the request cannot substitute a
     * private one. Not needed when the URL's host is already an IP.
     *
     * @return array<string, mixed>
     */
    protected function connectTo(string $url, string $address): array
    {
        $host = (string) parse_url($url, PHP_URL_HOST);

        if (! defined('CURLOPT_RESOLVE') || filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) !== false) {
            return [];
        }

        $port = parse_url($url, PHP_URL_PORT)
            ?: (strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https' ? 443 : 80);

        return ['curl' => [CURLOPT_RESOLVE => ["{$host}:{$port}:{$address}"]]];
    }

    /**
     * Retry a webhook delivery with exponential backoff.
     */
    protected function retryLater(Webhook $webhook, string $event, array $payload, int $attempt): void
    {
        $delaySeconds = (int) pow(2, $attempt) * 30; // 120s, 240s

        dispatch(function () use ($webhook, $event, $payload, $attempt) {
            $this->send($webhook, $event, $payload, $attempt);
        })->delay(now()->addSeconds($delaySeconds));
    }

    /**
     * Retry a specific delivery.
     */
    public function retryDelivery(WebhookDelivery $delivery): void
    {
        $webhook = $delivery->webhook;

        if ($webhook) {
            $this->send($webhook, $delivery->event, $delivery->payload ?? [], 1);
        }
    }
}
