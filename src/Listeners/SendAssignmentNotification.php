<?php

namespace Escalated\Laravel\Listeners;

use Escalated\Laravel\Escalated;
use Escalated\Laravel\Events\TicketAssigned;
use Escalated\Laravel\Notifications\TicketAssignedNotification;
use Escalated\Laravel\Support\ImportContext;
use Illuminate\Database\Eloquent\Model;

class SendAssignmentNotification
{
    public function handle(TicketAssigned $event): void
    {
        if (ImportContext::isImporting()) {
            return;
        }

        $userModel = Escalated::userModel();
        $agent = Escalated::findUser($event->agentId);

        // Assigning a ticket to yourself tells you nothing.
        $causer = $event->causer instanceof Model ? $event->causer->getKey() : $event->causer;
        if ($causer !== null && (string) $causer === (string) $event->agentId) {
            return;
        }

        if ($agent) {
            $agent->notify(new TicketAssignedNotification($event->ticket));
        }
    }
}
