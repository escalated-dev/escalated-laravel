<?php

namespace Escalated\Laravel\Services;

use Escalated\Laravel\Contracts\Ticketable;
use Escalated\Laravel\Enums\TicketPriority;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Models\Macro;
use Escalated\Laravel\Models\Ticket;

class MacroService
{
    public function __construct(
        protected TicketService $ticketService,
        protected AssignmentService $assignmentService,
    ) {}

    public function apply(Macro $macro, Ticket $ticket, Ticketable $causer): Ticket
    {
        foreach ($macro->actions as $action) {
            $type = $action['type'] ?? null;
            $value = $action['value'] ?? null;

            match ($type) {
                'status' => $this->ticketService->changeStatus($ticket, TicketStatus::from($value), $causer),
                'priority' => $this->ticketService->changePriority($ticket, TicketPriority::from($value), $causer),
                'assign' => $this->assignmentService->assign($ticket, $value, $causer),
                'tags' => $this->ticketService->addTags($ticket, (array) $value, $causer),
                'department' => $this->ticketService->changeDepartment($ticket, (int) $value, $causer),
                'reply' => $this->ticketService->reply($ticket, $causer, $value),
                'note' => $this->ticketService->addNote($ticket, $causer, $value),
                default => null,
            };

            $ticket->refresh();
        }

        return $ticket;
    }
}
