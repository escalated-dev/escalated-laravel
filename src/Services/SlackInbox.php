<?php

namespace Escalated\Laravel\Services;

use Escalated\Laravel\Escalated;
use Escalated\Laravel\Models\SlackInboundEvent;
use Escalated\Laravel\Tenancy\TenantContext;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;

/** Authenticated ingress only: no host identity lookup, HTTP call or ticket event on the acknowledgement path. */
class SlackInbox
{
    public function appConfig(string $app): array
    {
        abort_unless(config('escalated.slack.enabled', false) && config('escalated.mode', 'self-hosted') === 'self-hosted', 503);
        $apps = (array) config('escalated.slack.apps', []);
        $config = $apps[$app] ?? null;
        abort_unless(preg_match('/^[a-zA-Z0-9_-]{1,64}$/D', $app) && is_array($config)
            && is_string($config['signing_secret'] ?? null) && $config['signing_secret'] !== ''
            && is_string($config['app_id'] ?? null) && $config['app_id'] !== '', 503, 'Slack app is not configured.');

        return $config;
    }

    public function receive(string $app, string $raw, string $timestamp, string $signature): array
    {
        $config = $this->appConfig($app);
        abort_if(strlen($raw) > 1048576, 413);
        abort_unless(preg_match('/^[0-9]{1,12}$/D', $timestamp) && abs(now()->timestamp - (int) $timestamp) <= 300
            && preg_match('/^v0=[a-f0-9]{64}$/D', $signature)
            && hash_equals('v0='.hash_hmac('sha256', 'v0:'.$timestamp.':'.$raw, $config['signing_secret']), $signature), 401);
        try {
            $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            abort(400, 'Invalid Slack JSON.');
        }
        abort_unless(is_array($payload), 400);
        if (($payload['type'] ?? null) === 'url_verification') {
            abort_unless(is_string($payload['challenge'] ?? null) && strlen($payload['challenge']) <= 4096, 400);

            return ['challenge' => $payload['challenge']];
        }
        abort_unless(($payload['api_app_id'] ?? null) === $config['app_id'], 403);
        if (($payload['type'] ?? null) !== 'event_callback' || ! is_array($payload['event'] ?? null)) {
            return ['ignored' => true];
        }
        $event = $payload['event'];
        if (($event['type'] ?? null) !== 'message' || ! empty($event['subtype']) || ! empty($event['bot_id'])
            || ! empty($event['bot_profile']) || ! empty($event['hidden'])) {
            return ['ignored' => true];
        }
        foreach ([[$payload['team_id'] ?? null, '/^T[A-Za-z0-9]{1,63}$/D'],
            [$payload['event_id'] ?? null, '/^Ev[A-Za-z0-9]{1,126}$/D'],
            [$event['channel'] ?? null, '/^[CGD][A-Za-z0-9]{1,63}$/D'],
            [$event['user'] ?? null, '/^[UW][A-Za-z0-9]{1,63}$/D'],
            [$event['ts'] ?? null, '/^[0-9]{1,16}\.[0-9]{1,10}$/D'],
            [$event['thread_ts'] ?? $event['ts'] ?? null, '/^[0-9]{1,16}\.[0-9]{1,10}$/D']] as [$value, $pattern]) {
            abort_unless(is_string($value) && preg_match($pattern, $value), 400, 'Invalid Slack message identity.');
        }
        abort_unless(is_string($event['text'] ?? null), 400, 'Invalid Slack message text.');
        // Authenticated events this installation does not route are acknowledged,
        // not refused: Slack retries failures and can disable the subscription.
        // Text length is bounded only by the raw body limit; the processor
        // dead-letters text that cannot be stored, so none is silently lost.
        if (trim($event['text']) === '') {
            return $this->ignore('empty_text');
        }
        if (! is_array($config['channels'][$payload['team_id']][$event['channel']] ?? null)) {
            return $this->ignore('channel_not_mapped');
        }
        $destination = $this->destination($app, $payload['team_id'], $event['channel']);
        $store = fn () => $this->persist($app, $payload);
        $context = app(TenantContext::class);

        return $context->enabled() ? $context->run($destination['tenant_id'], $store) : $store();
    }

    public function destination(string $app, string $workspace, string $channel): array
    {
        $config = $this->appConfig($app);
        $destination = $config['channels'][$workspace][$channel] ?? null;
        abort_unless(is_array($destination), 403, 'Slack channel is not allowed.');
        if (app(TenantContext::class)->enabled()) {
            abort_unless(is_string($destination['tenant_id'] ?? null) && $destination['tenant_id'] !== '', 503, 'Slack tenant routing is not configured.');
        }

        return $destination;
    }

    public function receivePlugin(array $data): array
    {
        $raw = is_string($data['raw_body_base64'] ?? null) ? base64_decode($data['raw_body_base64'], true) : false;
        abort_unless(is_string($raw) && base64_encode($raw) === $data['raw_body_base64']
            && is_string($data['timestamp'] ?? null) && is_string($data['signature'] ?? null), 400);

        return $this->receive((string) config('escalated.slack.plugin_app', 'default'), $raw, $data['timestamp'], $data['signature']);
    }

    private function ignore(string $reason): array
    {
        Log::info('Slack inbound event ignored', ['reason' => $reason]);

        return ['ignored' => true];
    }

    private function persist(string $app, array $payload): array
    {
        // Returning 202 from a surrounding uncommitted transaction would lose
        // acknowledged events on rollback. Hosts must exclude this route from it.
        abort_unless(Escalated::db()->transactionLevel() === 0, 503, 'Slack ingress requires its own committed transaction.');
        $event = $payload['event'];
        $thread = $event['thread_ts'] ?? $event['ts'];
        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
        $messageKey = hash('sha256', $payload['team_id'].':'.$event['channel'].':'.$event['ts']);
        try {
            Escalated::db()->transaction(function () use ($app, $payload, $event, $thread, $hash, $messageKey) {
                $eventKey = hash('sha256', $payload['team_id'].':'.$payload['event_id']);
                $attributes = [
                    'event_key' => $eventKey,
                    'message_key' => $messageKey,
                    'app_key' => $app, 'workspace_id' => $payload['team_id'], 'channel_id' => $event['channel'],
                    'event_id' => $payload['event_id'], 'thread_ts' => $thread,
                    'thread_key' => hash('sha256', $payload['team_id'].':'.$event['channel'].':'.$thread),
                    'payload_hash' => $hash, 'payload' => $payload, 'status' => 'pending', 'available_at' => now(),
                ];
                try {
                    // The savepoint keeps PostgreSQL usable after a uniqueness
                    // conflict. Avoid a preliminary read snapshot on MySQL.
                    $record = Escalated::db()->transaction(fn () => SlackInboundEvent::create($attributes));
                } catch (UniqueConstraintViolationException $collision) {
                    // Locking reads see the committed winner even under MySQL
                    // REPEATABLE READ; an ordinary select can see a stale miss.
                    $record = SlackInboundEvent::where('event_key', $eventKey)->lockForUpdate()->first();
                    if (! $record) {
                        throw $collision;
                    }
                }
                abort_unless(hash_equals($record->payload_hash, $hash) && $record->app_key === $app, 409, 'Slack delivery conflicts with an existing receipt.');
            });
        } catch (UniqueConstraintViolationException) {
            // One app owns a channel's messages. Another configured app's copy of
            // an already recorded message is acknowledged without a second receipt.
            $owner = SlackInboundEvent::where('message_key', $messageKey)->value('app_key');
            if (is_string($owner) && $owner !== $app) {
                return $this->ignore('message_recorded_by_another_app');
            }
            // A message key collision or a routing change must not cross tenants.
            abort(409, 'Slack delivery conflicts with an existing receipt.');
        }

        return ['accepted' => true, 'event_id' => $payload['event_id']];
    }
}
