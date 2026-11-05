<?php

namespace Escalated\Laravel\Listeners;

use Escalated\Laravel\Escalated;
use Escalated\Laravel\Events\ChatTransferred;
use Escalated\Laravel\Events\SlaWarning;
use Escalated\Laravel\Notifications\ChatTransferredNotification;
use Escalated\Laravel\Notifications\SlaWarningNotification;
use Escalated\Laravel\Support\ImportContext;

/**
 * The in-app-only agent notifications: SLA warnings to the assignee and chat
 * transfers to the receiving agent (ADR 2026-11-05-agent-panel-data, section 4).
 */
class SendInAppAgentNotifications
{
    public function slaWarning(SlaWarning $event): void
    {
        if (ImportContext::isImporting()) {
            return;
        }

        if ($event->ticket->assigned_to && $assignee = $event->ticket->assignee) {
            $assignee->notify(new SlaWarningNotification($event->ticket, $event->type, $event->minutesRemaining));
        }
    }

    public function chatTransferred(ChatTransferred $event): void
    {
        // Nobody is told about a transfer to themselves.
        if ($event->toAgentId === null || $event->toAgentId === $event->fromAgentId || ! $event->session->ticket) {
            return;
        }

        $agent = Escalated::findUser($event->toAgentId);
        if (! $agent) {
            return;
        }

        $from = $event->fromAgentId !== null ? Escalated::findUser($event->fromAgentId)?->name : null;

        $agent->notify(new ChatTransferredNotification($event->session->ticket, $event->session->id, $from));
    }
}
