<?php

namespace Escalated\Laravel\Services;

use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Events\TicketEscalated;
use Escalated\Laravel\Models\EscalationRule;
use Escalated\Laravel\Models\RuleExecution;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Support\ImportContext;
use Escalated\Laravel\Support\TicketAccounts;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class EscalationService
{
    public function __construct(
        protected TicketService $ticketService,
        protected AssignmentService $assignmentService,
        protected ?SystemTicketActions $actions = null,
        protected ?RuleFireLog $fireLog = null,
    ) {
        $this->actions ??= new SystemTicketActions($ticketService, $assignmentService);
        $this->fireLog ??= new RuleFireLog;
    }

    /**
     * Run every active rule against the open tickets it matches.
     *
     * A rule acts on a ticket once; it acts again only after the ticket has
     * stopped matching, or after the rule's `repeat_after_hours`. A failure on
     * one ticket is logged and the sweep carries on with the next.
     *
     * Returns the number of rule/ticket pairs acted on.
     */
    public function evaluateRules(): int
    {
        if (ImportContext::isImporting()) {
            return 0;
        }

        $rules = EscalationRule::active()->get();
        $escalated = 0;

        foreach ($rules as $rule) {
            try {
                $tickets = $this->fireLog->due(
                    RuleExecution::ESCALATION_RULE,
                    $rule->getKey(),
                    $rule->repeat_after_hours,
                    $this->findMatchingTickets($rule),
                );
            } catch (\Throwable $e) {
                Log::error('Escalated escalation rule could not be evaluated', [
                    'rule_id' => $rule->getKey(),
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            foreach ($tickets as $ticket) {
                try {
                    $this->executeActions($ticket, $rule);
                    $this->fireLog->record(RuleExecution::ESCALATION_RULE, $rule->getKey(), $ticket);
                    $escalated++;
                } catch (\Throwable $e) {
                    Log::error('Escalated escalation rule failed for a ticket', [
                        'rule_id' => $rule->getKey(),
                        'ticket_id' => $ticket->getKey(),
                        'error' => $e->getMessage(),
                    ]);
                }
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
                'ticket_type' => $query->where('ticket_type', $condition['value']),
                'account' => TicketAccounts::constrain($query, $condition['value'] ?? null),
                'account_type', 'account_id' => TicketAccounts::whereIdentity($query, $condition['field'], $condition['value']),
                default => null,
            };
        }

        return $query->get();
    }

    protected function executeActions(Ticket $ticket, EscalationRule $rule): void
    {
        $context = ['escalation_rule_id' => $rule->getKey()];

        foreach ($rule->actions as $action) {
            $type = $action['type'] ?? '';
            $value = $action['value'] ?? null;

            try {
                $changed = match ($type) {
                    'escalate' => $this->escalate($ticket, $rule, $context),
                    'change_priority' => $this->actions->changePriority($ticket, $value, $context),
                    'assign_to' => $this->actions->assign($ticket, $value, $context),
                    'change_department' => $this->actions->changeDepartment($ticket, $value, $context),
                    default => null,
                };
            } catch (\Throwable $e) {
                $changed = null;
                Log::warning('Escalated escalation rule action failed', [
                    ...$context,
                    'ticket_id' => $ticket->getKey(),
                    'action' => $type,
                    'error' => $e->getMessage(),
                ]);
            }

            if ($changed instanceof Ticket) {
                $ticket = $changed;
            }
        }
    }

    /**
     * Escalate the ticket unless it is already escalated or the configured
     * transitions do not allow it (e.g. from waiting_on_customer). Dispatches
     * TicketEscalated once, with the rule name as the reason.
     */
    protected function escalate(Ticket $ticket, EscalationRule $rule, array $context): ?Ticket
    {
        if ($ticket->status === TicketStatus::Escalated) {
            return null;
        }

        $reason = "Escalation rule: {$rule->name}";
        $result = null;

        $dispatched = Ticket::withEscalationReason($reason, function () use ($ticket, $context, &$result) {
            $result = $this->actions->changeStatus($ticket, TicketStatus::Escalated, $context);
        });

        // A remote driver transitions the ticket without the local model
        // method, so nothing has announced the escalation yet.
        if ($result instanceof Ticket && ! $dispatched) {
            TicketEscalated::dispatch($result, $reason);
        }

        return $result;
    }
}
