<?php

namespace Escalated\Laravel\Services;

use Escalated\Laravel\Models\Automation;
use Escalated\Laravel\Models\RuleExecution;
use Escalated\Laravel\Models\Tag;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Support\ImportContext;
use Escalated\Laravel\Support\TicketAccounts;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class AutomationRunner
{
    public function __construct(
        protected SystemTicketActions $actions,
        protected RuleFireLog $fireLog,
    ) {}

    /**
     * Evaluate all active automations against open tickets.
     *
     * An automation acts on a ticket once; it acts again only after the ticket
     * has stopped matching, or after the automation's `repeat_after_hours`.
     */
    public function run(): int
    {
        if (ImportContext::isImporting()) {
            return 0;
        }

        $automations = Automation::active()->get();
        $affected = 0;

        foreach ($automations as $automation) {
            try {
                $tickets = $this->fireLog->due(
                    RuleExecution::AUTOMATION,
                    $automation->getKey(),
                    $automation->repeat_after_hours,
                    $this->findMatchingTickets($automation),
                );
            } catch (\Throwable $e) {
                Log::error('Escalated automation could not be evaluated', [
                    'automation_id' => $automation->getKey(),
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            foreach ($tickets as $ticket) {
                try {
                    $this->executeActions($automation, $ticket);
                    $this->fireLog->record(RuleExecution::AUTOMATION, $automation->getKey(), $ticket);
                    $affected++;
                } catch (\Throwable $e) {
                    Log::error('Escalated automation failed for a ticket', [
                        'automation_id' => $automation->getKey(),
                        'ticket_id' => $ticket->getKey(),
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $automation->update(['last_run_at' => now()]);
        }

        return $affected;
    }

    /**
     * Find open tickets matching the automation's conditions.
     */
    protected function findMatchingTickets(Automation $automation): Collection
    {
        $query = Ticket::open();

        foreach ($automation->conditions ?? [] as $condition) {
            $field = $condition['field'] ?? '';
            $operator = $condition['operator'] ?? '>';
            $value = $condition['value'] ?? null;

            switch ($field) {
                case 'hours_since_created':
                    $threshold = Carbon::now()->subHours((int) $value);
                    $query->where('created_at', $this->resolveOperator($operator), $threshold);
                    break;

                case 'hours_since_updated':
                    $threshold = Carbon::now()->subHours((int) $value);
                    $query->where('updated_at', $this->resolveOperator($operator), $threshold);
                    break;

                case 'hours_since_assigned':
                    // Approximation: use updated_at where assigned_to is set
                    $threshold = Carbon::now()->subHours((int) $value);
                    $query->whereNotNull('assigned_to')
                        ->where('updated_at', $this->resolveOperator($operator), $threshold);
                    break;

                case 'status':
                    $query->where('status', $value);
                    break;

                case 'priority':
                    $query->where('priority', $value);
                    break;

                case 'assigned':
                    if ($value === 'unassigned') {
                        $query->whereNull('assigned_to');
                    } elseif ($value === 'assigned') {
                        $query->whereNotNull('assigned_to');
                    }
                    break;

                case 'ticket_type':
                case 'department_id':
                    $query->where($field, $value);
                    break;

                case 'account_type':
                case 'account_id':
                    TicketAccounts::whereIdentity($query, $field, $value);
                    break;

                case 'account':
                    TicketAccounts::constrain($query, $value);
                    break;

                case 'subject_contains':
                    $query->where('subject', 'like', "%{$value}%");
                    break;
            }
        }

        return $query->get();
    }

    /**
     * Execute the automation's actions on a ticket.
     */
    protected function executeActions(Automation $automation, Ticket $ticket): void
    {
        $context = ['automation_id' => $automation->id];

        foreach ($automation->actions ?? [] as $action) {
            $type = $action['type'] ?? '';
            $value = $action['value'] ?? null;

            try {
                switch ($type) {
                    case 'change_status':
                        $ticket = $this->actions->changeStatus($ticket, $value, $context) ?? $ticket;
                        break;

                    case 'assign':
                        $ticket = $this->actions->assign($ticket, $value, $context) ?? $ticket;
                        break;

                    case 'add_tag':
                        $tag = Tag::where('name', $value)->first();
                        if ($tag) {
                            $ticket->tags()->syncWithoutDetaching([$tag->id]);
                        }
                        break;

                    case 'change_priority':
                        $ticket = $this->actions->changePriority($ticket, $value, $context) ?? $ticket;
                        break;

                    case 'add_note':
                        $ticket->replies()->create([
                            'body' => $value,
                            'is_internal_note' => true,
                            'is_pinned' => false,
                            'metadata' => ['system_note' => true, 'automation_id' => $automation->id],
                        ]);
                        break;

                    case 'set_ticket_type':
                        if (in_array($value, Ticket::TYPES, true)) {
                            $ticket->update(['ticket_type' => $value]);
                        }
                        break;
                }
            } catch (\Throwable $e) {
                Log::warning('Escalated automation action failed', [
                    'automation_id' => $automation->id,
                    'ticket_id' => $ticket->id,
                    'action' => $type,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Resolve a condition operator to a SQL comparison.
     * For hours_since fields, > hours means < datetime (older).
     */
    protected function resolveOperator(string $operator): string
    {
        return match ($operator) {
            '>' => '<',   // more hours ago = earlier datetime
            '>=' => '<=',
            '<' => '>',
            '<=' => '>=',
            '=' => '=',
            default => '<',
        };
    }
}
