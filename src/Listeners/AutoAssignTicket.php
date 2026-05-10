<?php

namespace Escalated\Laravel\Listeners;

use Escalated\Laravel\Events\TicketCreated;
use Escalated\Laravel\Services\AssignmentService;
use Escalated\Laravel\Support\ImportContext;

class AutoAssignTicket
{
    public function __construct(protected AssignmentService $assignmentService) {}

    public function handle(TicketCreated $event): void
    {
        if (ImportContext::isImporting()) {
            return;
        }

        $ticket = $event->ticket;

        if ($ticket->assigned_to === null) {
            $this->assignmentService->autoAssign($ticket);
        }
    }
}
