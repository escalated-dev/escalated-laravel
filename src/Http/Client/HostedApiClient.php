<?php

namespace Escalated\Laravel\Http\Client;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class HostedApiClient
{
    protected string $baseUrl;

    protected string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('escalated.hosted.api_url', 'https://cloud.escalated.dev/api/v1'), '/');
        $this->apiKey = config('escalated.hosted.api_key', '');
    }

    /**
     * Emit a Synced-mode event. `eventId` is echoed by the cloud and lets it
     * ignore a redelivery; pass the same id on every retry of one event.
     */
    public function emit(string $event, array $payload, ?string $eventId = null, ?string $timestamp = null): ?Response
    {
        $body = [
            'event' => $event,
            'payload' => $payload,
            'timestamp' => $timestamp ?? now()->toISOString(),
        ];

        if ($eventId !== null && $eventId !== '') {
            $body['event_id'] = $eventId;
        }

        return $this->post('/events', $body);
    }

    public function sendCommand(string $command, array $payload): ?Response
    {
        return $this->post('/commands', [
            'command' => $command,
            'payload' => $payload,
        ]);
    }

    public function query(string $endpoint, array $params = []): ?Response
    {
        return $this->get($endpoint, $params);
    }

    protected function post(string $endpoint, array $data): ?Response
    {
        return Http::withHeaders($this->headers())
            ->timeout(15)
            ->post($this->url($endpoint), $data);
    }

    protected function get(string $endpoint, array $params = []): ?Response
    {
        return Http::withHeaders($this->headers())
            ->timeout(15)
            ->get($this->url($endpoint), $params);
    }

    /**
     * Join the base URL and an endpoint whether or not the endpoint carries
     * a leading slash ("/events" and "tickets/7" are both valid callers).
     */
    protected function url(string $endpoint): string
    {
        return $this->baseUrl.'/'.ltrim($endpoint, '/');
    }

    protected function headers(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }
}
