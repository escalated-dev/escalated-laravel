<?php

use Escalated\Laravel\Models\ChatSession;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Support\StaffAccess;
use Escalated\Laravel\Tenancy\TenantBroadcast;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Gate;

$isAgent = static fn ($user): bool => StaffAccess::isStaff($user);
$isUser = static fn ($user, $id): bool => $id !== null && $id !== '' && (string) $user->getAuthIdentifier() === (string) $id;
$isPresence = static fn (): bool => str_starts_with((string) request('channel_name', ''), 'presence-');

// Register both forms so route caches do not embed the tenancy feature flag.
// Legacy channels fail closed whenever tenant isolation is enabled.
foreach (['escalated' => false, 'escalated.tenants.{namespace}' => true] as $prefix => $namespaced) {
    $register = static function (string $suffix, Closure $callback) use ($prefix, $namespaced) {
        Broadcast::channel($prefix.'.'.$suffix, static function ($user, ...$parameters) use ($callback, $namespaced) {
            $namespace = $namespaced ? array_shift($parameters) : null;

            return TenantBroadcast::authorize($user, $namespace, fn () => $callback($user, ...$parameters));
        });
    };
    $register('tickets', fn ($user) => ! $isPresence() && $isAgent($user));
    $register('tickets.{ticketId}', static function ($user, $ticketId) use ($isPresence, $isAgent) {
        if (! ctype_digit((string) $ticketId) || ! ($ticket = Ticket::find($ticketId))) {
            return false;
        }
        if (! Gate::forUser($user)->allows('view', $ticket)) {
            return false;
        }
        // Laravel strips the presence-/private- prefix before pattern matching.
        // Requesters may receive public updates, but must not join agent presence.
        if ($isPresence()) {
            return $isAgent($user) ? ['id' => $user->getAuthIdentifier(), 'name' => $user->name] : false;
        }

        return true;
    });
    $register('agents.{agentId}', fn ($user, $agentId) => ! $isPresence() && $isUser($user, $agentId));
    // Exact match must precede the session wildcard ("queue" is not a session ID).
    $register('chat.queue', fn ($user) => ! $isPresence() && $isAgent($user));
    $register('chat.{sessionId}', static function ($user, $sessionId) use ($isPresence, $isAgent, $isUser) {
        if ($isPresence() || ! ctype_digit((string) $sessionId) || ! ($session = ChatSession::find($sessionId))) {
            return false;
        }

        return $isUser($user, $session->agent_id) || $isAgent($user);
    });
}
