<?php

namespace Escalated\Laravel\Services;

use Escalated\Laravel\Models\Automation;
use Escalated\Laravel\Models\Tag;
use Escalated\Laravel\Models\Ticket;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class AutomationRunner
{
    /**
     * Evaluate all active automations against open tickets.
     */
    public function run(): int
    {
        $automations = Automation::active()->get();
        $affected = 0;

        foreach ($automations as $automation) {
            $tickets = $this->findMatchingTickets($automation);

            foreach ($tickets as $ticket) {
                $this->executeActions($automation, $ticket);
                $affected++;
            }

            $automation->update(['last_run_at' => now()]);
        }

        return $affected;
    }

    /**
     * Find open tickets matching the automation's conditions.
     */
    protected function findMatchingTickets(Automation $automation): \Illuminate\Support\Collection
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
                    $query->where('ticket_type', $value);
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
        foreach ($automation->actions ?? [] as $action) {
            $type = $action['type'] ?? '';
            $value = $action['value'] ?? null;

            try {
                switch ($type) {
                    case 'change_status':
                        $ticket->update(['status' => $value]);
                        break;

                    case 'assign':
                        $ticket->update(['assigned_to' => (int) $value]);
                        break;

                    case 'add_tag':
                        $tag = Tag::where('name', $value)->first();
                        if ($tag) {
                            $ticket->tags()->syncWithoutDetaching([$tag->id]);
                        }
                        break;

                    case 'change_priority':
                        $ticket->update(['priority' => $value]);
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
