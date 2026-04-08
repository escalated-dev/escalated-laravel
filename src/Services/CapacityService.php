<?php

namespace Escalated\Laravel\Services;

use Escalated\Laravel\Models\AgentCapacity;
use Illuminate\Support\Collection;

class CapacityService
{
    /**
     * Check if an agent can accept a new ticket.
     */
    public function canAcceptTicket(int $userId, string $channel = 'default'): bool
    {
        $capacity = AgentCapacity::firstOrCreate(
            ['user_id' => $userId, 'channel' => $channel],
            ['max_concurrent' => 10, 'current_count' => 0]
        );

        return $capacity->hasCapacity();
    }

    /**
     * Increment the agent's current load.
     */
    public function incrementLoad(int $userId, string $channel = 'default'): void
    {
        $capacity = AgentCapacity::firstOrCreate(
            ['user_id' => $userId, 'channel' => $channel],
            ['max_concurrent' => 10, 'current_count' => 0]
        );

        $capacity->increment('current_count');
    }

    /**
     * Decrement the agent's current load.
     */
    public function decrementLoad(int $userId, string $channel = 'default'): void
    {
        $capacity = AgentCapacity::firstOrCreate(
            ['user_id' => $userId, 'channel' => $channel],
            ['max_concurrent' => 10, 'current_count' => 0]
        );

        if ($capacity->current_count > 0) {
            $capacity->decrement('current_count');
        }
    }

    /**
     * Get all agent capacities for admin view.
     */
    public function getAllCapacities(): Collection
    {
        return AgentCapacity::with('user')->get();
    }
}
