<?php

namespace Escalated\Laravel\Services;

use Escalated\Laravel\Enums\ChatSessionStatus;
use Escalated\Laravel\Enums\ChatStatus;
use Escalated\Laravel\Enums\RoutingStrategy;
use Escalated\Laravel\Escalated;
use Escalated\Laravel\Models\AgentProfile;
use Escalated\Laravel\Models\ChatRoutingRule;
use Escalated\Laravel\Models\ChatSession;
use Illuminate\Database\Eloquent\Model;

class ChatRoutingService
{
    /**
     * Find an available agent, optionally scoped to a department.
     */
    public function findAvailableAgent(?int $departmentId = null): ?Model
    {
        $rule = $this->getRoutingRule($departmentId);
        $maxConcurrent = $rule?->max_concurrent_per_agent ?? 5;
        $strategy = $rule?->routing_strategy ?? RoutingStrategy::RoundRobin;

        $onlineProfiles = AgentProfile::where('chat_status', ChatStatus::Online->value)->get();

        if ($onlineProfiles->isEmpty()) {
            return null;
        }

        $userModel = Escalated::userModel();

        // Filter by department if needed
        $agentIds = $onlineProfiles->pluck('user_id')->toArray();

        if ($departmentId) {
            // Only include agents that belong to the department (via ticket assignments)
            // In practice this could use a department_agents pivot; for now we keep it simple
        }

        // Filter out agents at capacity
        $availableAgentIds = [];
        foreach ($agentIds as $agentId) {
            if ($this->getAgentChatCount($agentId) < $maxConcurrent) {
                $availableAgentIds[] = $agentId;
            }
        }

        if (empty($availableAgentIds)) {
            return null;
        }

        return match ($strategy) {
            RoutingStrategy::LeastBusy => $this->findLeastBusyAgent($availableAgentIds, $userModel),
            RoutingStrategy::RoundRobin => $this->findRoundRobinAgent($availableAgentIds, $userModel),
            RoutingStrategy::SkillBased => $this->findRoundRobinAgent($availableAgentIds, $userModel),
            RoutingStrategy::AutoAssign => $userModel::whereIn('id', $availableAgentIds)->first(),
            RoutingStrategy::ManualQueue => null,
        };
    }

    /**
     * Evaluate routing rules and assign an agent if possible.
     */
    public function evaluateRouting(ChatSession $session): void
    {
        $rule = $this->getRoutingRule($session->ticket->department_id ?? null);

        $strategy = $rule?->routing_strategy ?? RoutingStrategy::RoundRobin;

        if ($strategy === RoutingStrategy::ManualQueue) {
            return;
        }

        $agent = $this->findAvailableAgent($session->ticket->department_id ?? null);

        if ($agent) {
            $session->update([
                'agent_id' => $agent->id,
                'status' => ChatSessionStatus::Active,
            ]);

            $session->ticket->update(['assigned_to' => $agent->id]);
        }
    }

    /**
     * Get the active routing rule for a department (or the global default).
     */
    public function getRoutingRule(?int $departmentId = null): ?ChatRoutingRule
    {
        if ($departmentId) {
            $rule = ChatRoutingRule::active()
                ->where('department_id', $departmentId)
                ->ordered()
                ->first();

            if ($rule) {
                return $rule;
            }
        }

        return ChatRoutingRule::active()
            ->whereNull('department_id')
            ->ordered()
            ->first();
    }

    /**
     * Get the queue position for a waiting chat session.
     */
    public function getQueuePosition(ChatSession $session): int
    {
        return ChatSession::waiting()
            ->where('started_at', '<', $session->started_at)
            ->count() + 1;
    }

    /**
     * Get the number of active chat sessions for a specific agent.
     */
    public function getAgentChatCount(int $agentId): int
    {
        return ChatSession::active()->forAgent($agentId)->count();
    }

    protected function findLeastBusyAgent(array $agentIds, string $userModel): ?Model
    {
        $leastBusyId = null;
        $minCount = PHP_INT_MAX;

        foreach ($agentIds as $agentId) {
            $count = $this->getAgentChatCount($agentId);
            if ($count < $minCount) {
                $minCount = $count;
                $leastBusyId = $agentId;
            }
        }

        return $leastBusyId ? $userModel::find($leastBusyId) : null;
    }

    protected function findRoundRobinAgent(array $agentIds, string $userModel): ?Model
    {
        // Find agent who was last assigned a chat the longest ago
        $lastAssigned = ChatSession::whereIn('agent_id', $agentIds)
            ->orderByDesc('created_at')
            ->pluck('agent_id')
            ->unique()
            ->values()
            ->toArray();

        // Find agents who have never been assigned (prefer them first)
        $neverAssigned = array_diff($agentIds, $lastAssigned);

        if (! empty($neverAssigned)) {
            return $userModel::find(reset($neverAssigned));
        }

        // Pick the agent who was assigned longest ago (last in the recent list)
        $leastRecent = collect($lastAssigned)->intersect($agentIds)->last();

        return $leastRecent ? $userModel::find($leastRecent) : $userModel::find(reset($agentIds));
    }
}
