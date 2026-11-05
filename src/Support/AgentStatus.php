<?php

namespace Escalated\Laravel\Support;

use Escalated\Laravel\Enums\ChatStatus;
use Escalated\Laravel\Models\AgentProfile;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The agent's own Online / Away / Offline status (ADR
 * 2026-11-05-agent-panel-data). It is stored in the agent profile's
 * `chat_status`, the field live-chat routing reads, so there is one status per
 * agent whichever route set it.
 */
final class AgentStatus
{
    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (ChatStatus $status) => $status->value, ChatStatus::cases());
    }

    /** The agent's status; `offline` without a profile, as chat routing assumes. */
    public static function for(Authenticatable $agent): string
    {
        $profile = AgentProfile::where('user_id', $agent->getAuthIdentifier())->first();

        return $profile?->chat_status?->value ?? ChatStatus::Offline->value;
    }

    public static function set(Authenticatable $agent, ChatStatus $status): void
    {
        AgentProfile::forUser($agent->getAuthIdentifier())->update(['chat_status' => $status]);
    }
}
