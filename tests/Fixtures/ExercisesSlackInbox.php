<?php

namespace Escalated\Laravel\Tests\Fixtures;

use Escalated\Laravel\Contracts\TenantResolver;
use Escalated\Laravel\Escalated;
use Escalated\Laravel\Tenancy\TenantContext;
use Escalated\Laravel\Tenancy\TenantTables;
use Illuminate\Database\DatabaseTransactionsManager;
use Illuminate\Support\Facades\Gate;

trait ExercisesSlackInbox
{
    public function prepareSlackInbox(): void
    {
        // HTTP acknowledgement requires a real outer commit. Leave the host's
        // separate transaction intact; end only the empty package fixture TX.
        Escalated::db()->rollBack(0);
        // Testbench's manager intentionally fires callbacks above its usual
        // enclosing test transaction. This fixture has no such transaction.
        Escalated::db()->setTransactionManager(new DatabaseTransactionsManager);
        $this->actor = $this->createAgent();
        $this->recipient = $this->createTestUser(['email' => 'recipient@example.test']);
        Gate::define('escalated-agent', fn ($user) => (bool) $user->is_agent);
        Gate::define('escalated-admin', fn ($user) => (bool) $user->is_admin);
        config(['escalated.slack.enabled' => true, 'escalated.slack.apps' => ['default' => [
            'signing_secret' => 'fixture-secret', 'app_id' => 'A123',
            'channels' => ['T123' => ['C123' => ['tenant_id' => 'a', 'actor_id' => $this->actor->id]]],
            'users' => ['T123' => ['U123' => ['id' => $this->recipient->id]]],
        ]]]);
    }

    public function cleanSlackInbox(): void
    {
        config(['escalated.tenancy.enabled' => false]);
        Escalated::db()->rollBack(0);
        // These are Testbench's explicitly configured test databases. Remove
        // committed fixtures before its migration rollback ownership guard.
        Escalated::schema()->disableForeignKeyConstraints();
        try {
            foreach (array_reverse(TenantTables::NAMES) as $table) {
                if (Escalated::schema()->hasTable(Escalated::table($table))) {
                    Escalated::db()->table(Escalated::table($table))->delete();
                }
            }
        } finally {
            Escalated::schema()->enableForeignKeyConstraints();
        }
    }

    public function slackPayload(array $replace = []): array
    {
        return array_replace_recursive(['type' => 'event_callback', 'api_app_id' => 'A123', 'event_id' => 'Ev123',
            'team_id' => 'T123', 'event' => ['type' => 'message', 'channel' => 'C123', 'user' => 'U123',
                'ts' => '1234567890.000100', 'text' => 'Parcel <script>alert(1)</script> 📦']], $replace);
    }

    public function slackRequest(?array $payload = null, ?int $timestamp = null, array $headers = [])
    {
        $raw = ' '.json_encode($payload ?? $this->slackPayload(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\r\n";
        $timestamp ??= now()->timestamp;

        return $this->call('POST', '/support/inbound/slack/default', [], [], [], array_replace([
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_SLACK_REQUEST_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_SLACK_SIGNATURE' => 'v0='.hash_hmac('sha256', 'v0:'.$timestamp.':'.$raw, 'fixture-secret'),
        ], $headers), $raw);
    }

    public function enableSlackTenancy(): TenantContext
    {
        $resolver = new ApiTenantResolver;
        $resolver->members = ['a' => [$this->actor->id, $this->recipient->id], 'b' => []];
        app()->instance(TenantResolver::class, $resolver);
        config(['escalated.tenancy.enabled' => true]);

        return app(TenantContext::class);
    }
}
