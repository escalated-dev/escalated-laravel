<?php

namespace Escalated\Laravel\Services;

use Escalated\Laravel\Enums\ChatStatus;
use Escalated\Laravel\Models\AgentProfile;
use Escalated\Laravel\Models\ChatSession;
use Illuminate\Support\Collection;

class ChatAvailabilityService
{
    public function __construct(
        protected ChatRoutingService $routingService
    ) {}

    /**
     * Check if chat is available for a given department (any online agents under capacity?).
     */
    public function isAvailable(?int $departmentId = null): bool
    {
        $onlineAgents = $this->getOnlineAgents($departmentId);

        if ($onlineAgents->isEmpty()) {
            return false;
        }

        $rule = $this->routingService->getRoutingRule($departmentId);
        $maxConcurrent = $rule?->max_concurrent_per_agent ?? 5;

        foreach ($onlineAgents as $agentProfile) {
            if ($this->getAgentChatCount($agentProfile->user_id) < $maxConcurrent) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get online agent profiles, optionally scoped to a department.
     */
    public function getOnlineAgents(?int $departmentId = null): Collection
    {
        return AgentProfile::where('chat_status', ChatStatus::Online->value)->get();
    }

    /**
     * Get the number of active chat sessions for a specific agent.
     */
    public function getAgentChatCount(int $agentId): int
    {
        return ChatSession::active()->forAgent($agentId)->count();
    }
}
