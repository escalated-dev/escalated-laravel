<?php

namespace Escalated\Laravel\Services;

use Escalated\Laravel\Enums\TicketPriority;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Events\TicketEscalated;
use Escalated\Laravel\Models\EscalationRule;
use Escalated\Laravel\Models\Ticket;
use Illuminate\Support\Collection;

class EscalationService
{
    public function __construct(
        protected TicketService $ticketService,
        protected AssignmentService $assignmentService,
    ) {}

    public function evaluateRules(): int
    {
        $rules = EscalationRule::active()->get();
        $escalated = 0;

        foreach ($rules as $rule) {
            $tickets = $this->findMatchingTickets($rule);

            foreach ($tickets as $ticket) {
                $this->executeActions($ticket, $rule);
                $escalated++;
            }
        }

        return $escalated;
    }

    protected function findMatchingTickets(EscalationRule $rule): Collection
    {
        $query = Ticket::open();

        foreach ($rule->conditions as $condition) {
            match ($condition['field'] ?? '') {
                'status' => $query->where('status', $condition['value']),
                'priority' => $query->where('priority', $condition['value']),
                'assigned' => $condition['value'] === 'unassigned'
                    ? $query->whereNull('assigned_to')
                    : $query->whereNotNull('assigned_to'),
                'age_hours' => $query->where('created_at', '<=', now()->subHours((int) $condition['value'])),
                'no_response_hours' => $query->whereNull('first_response_at')
                    ->where('created_at', '<=', now()->subHours((int) $condition['value'])),
                'sla_breached' => $query->breachedSla(),
                'department_id' => $query->where('department_id', $condition['value']),
                default => null,
            };
        }

        return $query->get();
    }

    protected function executeActions(Ticket $ticket, EscalationRule $rule): void
    {
        foreach ($rule->actions as $action) {
            match ($action['type'] ?? '') {
                'escalate' => $this->ticketService->changeStatus($ticket, TicketStatus::Escalated),
                'change_priority' => $this->ticketService->changePriority(
                    $ticket, TicketPriority::from($action['value'])
                ),
                'assign_to' => $this->assignmentService->assign($ticket, (int) $action['value']),
                'change_department' => $this->ticketService->changeDepartment($ticket, (int) $action['value']),
                default => null,
            };
        }

        if (collect($rule->actions)->contains('type', 'escalate')) {
            TicketEscalated::dispatch($ticket, "Escalation rule: {$rule->name}");
        }
    }
}
