<?php

namespace Escalated\Laravel\Listeners;

use Escalated\Laravel\Enums\ActivityType;
use Escalated\Laravel\Events\TicketStatusChanged;

class LogTicketStatusChange
{
    public function __construct() {}

    public function handle(TicketStatusChanged $event): void
    {
        $event->ticket->logActivity(ActivityType::StatusChanged, $event->causer, [
            'old_status' => $event->oldStatus->value,
            'new_status' => $event->newStatus->value,
        ]);
    }
}
