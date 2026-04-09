<?php

namespace Escalated\Laravel\Listeners;

use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Events\TicketStatusChanged;
use Escalated\Laravel\Models\DelayedAction;

class CancelDelayedActionsOnClose
{
    public function handle(TicketStatusChanged $event): void
    {
        $closedStatuses = [TicketStatus::Resolved, TicketStatus::Closed];

        if (in_array($event->newStatus, $closedStatuses)) {
            DelayedAction::where('ticket_id', $event->ticket->id)
                ->pending()
                ->update(['cancelled' => true]);
        }
    }
}
