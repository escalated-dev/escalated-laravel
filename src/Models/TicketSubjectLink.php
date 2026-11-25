<?php

namespace Escalated\Laravel\Models;

use Escalated\Laravel\Concerns\UsesEscalatedConnection;
use Escalated\Laravel\Contracts\TicketSubject;
use Escalated\Laravel\Escalated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Join row linking a ticket to one host-app subject model. The `subject`
 * relation resolves polymorphically to the attached host model (a Project,
 * Customer, …), which should implement
 * {@see TicketSubject}.
 */
class TicketSubjectLink extends Model
{
    use UsesEscalatedConnection;

    protected $guarded = ['id'];

    /**
     * What this link serialises as, when set (the ticket pages set the API
     * resource's shape). Null keeps the model's usual serialisation.
     *
     * @var array<string, mixed>|null
     */
    protected ?array $presentation = null;

    /** @param  array<string, mixed>|null  $presentation */
    public function presentAs(?array $presentation): static
    {
        $this->presentation = $presentation;

        return $this;
    }

    public function toArray(): array
    {
        return $this->presentation ?? parent::toArray();
    }

    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    public function getTable(): string
    {
        return Escalated::table('ticket_subjects');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
