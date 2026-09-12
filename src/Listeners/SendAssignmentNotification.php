<?php

namespace Escalated\Laravel\Listeners;

use Escalated\Laravel\Escalated;
use Escalated\Laravel\Events\TicketAssigned;
use Escalated\Laravel\Notifications\TicketAssignedNotification;
use Escalated\Laravel\Support\ImportContext;

class SendAssignmentNotification
{
    public function handle(TicketAssigned $event): void
    {
        if (ImportContext::isImporting()) {
            return;
        }

        $userModel = Escalated::userModel();
        $agent = Escalated::findUser($event->agentId);

        if ($agent) {
            $agent->notify(new TicketAssignedNotification($event->ticket));
        }
    }
}
