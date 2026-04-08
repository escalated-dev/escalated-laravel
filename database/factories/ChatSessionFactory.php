<?php

namespace Escalated\Laravel\Database\Factories;

use Escalated\Laravel\Enums\ChatSessionStatus;
use Escalated\Laravel\Models\ChatSession;
use Escalated\Laravel\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ChatSessionFactory extends Factory
{
    protected $model = ChatSession::class;

    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'customer_session_id' => Str::random(64),
            'agent_id' => null,
            'status' => ChatSessionStatus::Waiting,
            'started_at' => now(),
            'ended_at' => null,
            'metadata' => null,
        ];
    }

    public function waiting(): static
    {
        return $this->state([
            'status' => ChatSessionStatus::Waiting,
        ]);
    }

    public function active(?int $agentId = 1): static
    {
        return $this->state([
            'status' => ChatSessionStatus::Active,
            'agent_id' => $agentId,
        ]);
    }

    public function ended(): static
    {
        return $this->state([
            'status' => ChatSessionStatus::Ended,
            'ended_at' => now(),
        ]);
    }

    public function forAgent(int $agentId): static
    {
        return $this->state([
            'agent_id' => $agentId,
        ]);
    }
}
