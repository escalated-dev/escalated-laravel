<?php

namespace Escalated\Laravel\Services;

use Escalated\Laravel\Enums\TicketPriority;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Models\Department;
use Escalated\Laravel\Models\Ticket;
use Illuminate\Support\Facades\Log;

/**
 * Ticket changes made by the system (workflows, automations, escalation rules,
 * macros applied by a workflow).
 *
 * Every change goes through the same domain methods an agent uses, so status
 * transitions are checked, resolved_at/closed_at are stamped, activity is
 * logged and the status/priority/assignment/department events fire. A change
 * to the value the ticket already has is a no-op, and a value the domain
 * rejects (an unknown status, a transition the workflow config does not allow,
 * a missing department or user) is logged and skipped rather than thrown, so
 * one bad action does not stop the rest.
 *
 * Each method returns the refreshed ticket when it changed something, or null.
 */
class SystemTicketActions
{
    public function __construct(
        protected TicketService $tickets,
        protected AssignmentService $assignments,
    ) {}

    public function changeStatus(Ticket $ticket, mixed $value, array $context = []): ?Ticket
    {
        $status = $value instanceof TicketStatus ? $value : TicketStatus::tryFrom((string) $value);

        if ($status === null) {
            return $this->skip('unknown status', $ticket, $value, $context);
        }

        if ($ticket->status === $status) {
            return null;
        }

        if (! $ticket->status->canTransitionTo($status)) {
            return $this->skip("transition {$ticket->status->value} -> {$status->value} is not allowed", $ticket, $value, $context);
        }

        return $this->tickets->changeStatus($ticket, $status);
    }

    public function changePriority(Ticket $ticket, mixed $value, array $context = []): ?Ticket
    {
        $priority = $value instanceof TicketPriority ? $value : TicketPriority::tryFrom((string) $value);

        if ($priority === null) {
            return $this->skip('unknown priority', $ticket, $value, $context);
        }

        if ($ticket->priority === $priority) {
            return null;
        }

        return $this->tickets->changePriority($ticket, $priority);
    }

    public function assign(Ticket $ticket, mixed $agentId, array $context = []): ?Ticket
    {
        $agentId = is_string($agentId) ? trim($agentId) : $agentId;

        if ($agentId === null || $agentId === '' || ! (is_int($agentId) || is_string($agentId))) {
            return $this->skip('missing agent id', $ticket, $agentId, $context);
        }

        if ((string) $ticket->assigned_to === (string) $agentId) {
            return null;
        }

        try {
            return $this->assignments->assign($ticket, $agentId);
        } catch (\InvalidArgumentException $e) {
            return $this->skip($e->getMessage(), $ticket, $agentId, $context);
        }
    }

    public function changeDepartment(Ticket $ticket, mixed $value, array $context = []): ?Ticket
    {
        $departmentId = is_array($value) ? ($value['department_id'] ?? null) : $value;

        if (! is_numeric($departmentId) || (int) $departmentId <= 0) {
            return $this->skip('invalid department id', $ticket, $value, $context);
        }

        $departmentId = (int) $departmentId;

        if ((int) $ticket->department_id === $departmentId) {
            return null;
        }

        if (! Department::query()->whereKey($departmentId)->exists()) {
            return $this->skip("no department found with ID {$departmentId}", $ticket, $value, $context);
        }

        return $this->tickets->changeDepartment($ticket, $departmentId);
    }

    protected function skip(string $reason, Ticket $ticket, mixed $value, array $context): null
    {
        Log::warning('Escalated system ticket action skipped: '.$reason, [
            ...$context,
            'ticket_id' => $ticket->getKey(),
            'value' => is_scalar($value) || $value === null ? $value : json_encode($value),
        ]);

        return null;
    }
}
