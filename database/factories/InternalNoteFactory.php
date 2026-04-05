<?php

namespace Escalated\Laravel\Database\Factories;

use Escalated\Laravel\Models\InternalNote;
use Escalated\Laravel\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

class InternalNoteFactory extends Factory
{
    protected $model = InternalNote::class;

    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'author_type' => 'App\\Models\\User',
            'author_id' => 1,
            'body' => $this->faker->paragraphs(2, true),
            'type' => 'note',
        ];
    }

    public function systemMessage(): static
    {
        return $this->state(fn () => [
            'type' => 'system',
            'body' => 'Status changed to resolved.',
        ]);
    }
}
