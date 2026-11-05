<?php

namespace Escalated\Laravel\Events;

use Escalated\Laravel\Events\Concerns\BroadcastsWhenEnabled;
use Escalated\Laravel\Tenancy\TenantBroadcast;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A new in-app notification for an agent, on their private channel
 * (`escalated.agents.{id}`), when broadcasting is enabled. The payload is the
 * feed item the notifications route lists.
 */
class AgentNotificationCreated implements ShouldBroadcastNow
{
    use BroadcastsWhenEnabled, Dispatchable;

    public function __construct(
        public int|string $agentId,
        public array $item,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel(TenantBroadcast::channel('agents.'.$this->agentId))];
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    public function broadcastWith(): array
    {
        return $this->item;
    }
}
