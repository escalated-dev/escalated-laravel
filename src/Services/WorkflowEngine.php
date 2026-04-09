<?php

namespace Escalated\Laravel\Services;

use Escalated\Laravel\Enums\TicketPriority;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Escalated;
use Escalated\Laravel\Models\CustomFieldValue;
use Escalated\Laravel\Models\DelayedAction;
use Escalated\Laravel\Models\Macro;
use Escalated\Laravel\Models\Tag;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Models\Workflow;
use Escalated\Laravel\Models\WorkflowLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WorkflowEngine
{
    /**
     * Process all active workflows for a given event and ticket.
     */
    public function processEvent(string $event, Ticket $ticket, array $context = []): void
    {
        $workflows = Workflow::active()->forEvent($event)->get();

        foreach ($workflows as $workflow) {
            $this->runWorkflow($workflow, $ticket, $event, $context);
        }
    }

    /**
     * Run a single workflow against a ticket, logging the result.
     */
    protected function runWorkflow(Workflow $workflow, Ticket $ticket, string $event, array $context = []): void
    {
        $startedAt = now();

        $log = WorkflowLog::create([
            'workflow_id' => $workflow->id,
            'ticket_id' => $ticket->id,
            'trigger_event' => $event,
            'conditions_matched' => false,
            'actions_executed' => [],
            'started_at' => $startedAt,
        ]);

        try {
            $matched = $this->evaluateConditions($workflow->conditions ?? [], $ticket);
            $log->update(['conditions_matched' => $matched]);

            if ($matched) {
                $this->executeActions($workflow, $ticket, $workflow->actions ?? []);

                $workflow->increment('trigger_count');
                $workflow->update(['last_triggered_at' => now()]);
            }

            $log->update(['completed_at' => now()]);
        } catch (\Throwable $e) {
            $log->update([
                'error' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            Log::warning('Escalated workflow failed', [
                'workflow_id' => $workflow->id,
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Evaluate condition groups against a ticket.
     */
    public function evaluateConditions(array $conditions, Ticket $ticket): bool
    {
        if (empty($conditions)) {
            return true;
        }

        $match = $conditions['match'] ?? 'all';
        $rules = $conditions['rules'] ?? [];

        if (empty($rules)) {
            return true;
        }

        foreach ($rules as $rule) {
            $result = $this->evaluateRule($rule, $ticket);

            if ($match === 'any' && $result) {
                return true;
            }

            if ($match === 'all' && ! $result) {
                return false;
            }
        }

        return $match === 'all';
    }

    /**
     * Evaluate a single condition rule.
     */
    protected function evaluateRule(array $rule, Ticket $ticket): bool
    {
        $field = $rule['field'] ?? '';
        $operator = $rule['operator'] ?? 'equals';
        $value = $rule['value'] ?? null;

        $actual = $this->resolveFieldValue($field, $ticket);

        return $this->compareValues($actual, $operator, $value);
    }

    /**
     * Resolve the actual value of a condition field from a ticket.
     */
    public function resolveFieldValue(string $field, Ticket $ticket): mixed
    {
        if (Str::startsWith($field, 'department.')) {
            $sub = Str::after($field, 'department.');
            $department = $ticket->department;

            return $department?->{$sub};
        }

        if (Str::startsWith($field, 'requester.')) {
            $sub = Str::after($field, 'requester.');
            $requester = $ticket->requester;

            if ($requester) {
                return $requester->{$sub};
            }

            if ($sub === 'email') {
                return $ticket->guest_email;
            }
            if ($sub === 'name') {
                return $ticket->guest_name;
            }

            return null;
        }

        if (Str::startsWith($field, 'custom_field.')) {
            $fieldName = Str::after($field, 'custom_field.');

            return $this->getCustomFieldValue($ticket, $fieldName);
        }

        if (Str::startsWith($field, 'sla.')) {
            $sub = Str::after($field, 'sla.');

            return match ($sub) {
                'breached' => $ticket->sla_first_response_breached || $ticket->sla_resolution_breached,
                'warning' => $ticket->first_response_due_at?->diffInMinutes(now(), false) < 0
                    && $ticket->first_response_due_at?->diffInMinutes(now(), false) > -30,
                default => null,
            };
        }

        return match ($field) {
            'status' => $ticket->status instanceof TicketStatus ? $ticket->status->value : $ticket->status,
            'priority' => $ticket->priority instanceof TicketPriority ? $ticket->priority->value : $ticket->priority,
            'type' => $ticket->ticket_type,
            'channel' => $ticket->channel?->value ?? $ticket->channel,
            'tags' => $ticket->tags->pluck('name')->toArray(),
            'assigned_to' => $ticket->assigned_to,
            'subject' => $ticket->subject,
            'hours_since_created' => $ticket->created_at ? $ticket->created_at->diffInHours(now()) : 0,
            'hours_since_updated' => $ticket->updated_at ? $ticket->updated_at->diffInHours(now()) : 0,
            'hours_since_assigned' => $ticket->assigned_to && $ticket->updated_at
                ? $ticket->updated_at->diffInHours(now())
                : 0,
            'reply_count' => $ticket->replies()->where('is_internal_note', false)->count(),
            'is_first_reply' => $ticket->replies()->where('is_internal_note', false)->count() <= 1,
            default => $ticket->{$field} ?? null,
        };
    }

    protected function getCustomFieldValue(Ticket $ticket, string $fieldName): mixed
    {
        $cfv = CustomFieldValue::whereHas('customField', function ($q) use ($fieldName) {
            $q->where('name', $fieldName);
        })
            ->where('entity_type', $ticket->getMorphClass())
            ->where('entity_id', $ticket->id)
            ->first();

        return $cfv?->value;
    }

    /**
     * Compare actual value against expected using operator.
     */
    public function compareValues(mixed $actual, string $operator, mixed $expected): bool
    {
        return match ($operator) {
            'equals' => $this->looseEquals($actual, $expected),
            'not_equals' => ! $this->looseEquals($actual, $expected),
            'contains' => $this->valueContains($actual, $expected),
            'not_contains' => ! $this->valueContains($actual, $expected),
            'in' => is_array($expected) && in_array($actual, $expected, false),
            'not_in' => is_array($expected) && ! in_array($actual, $expected, false),
            'greater_than' => is_numeric($actual) && is_numeric($expected) && $actual > $expected,
            'less_than' => is_numeric($actual) && is_numeric($expected) && $actual < $expected,
            'greater_than_or_equal' => is_numeric($actual) && is_numeric($expected) && $actual >= $expected,
            'less_than_or_equal' => is_numeric($actual) && is_numeric($expected) && $actual <= $expected,
            'is_empty' => empty($actual),
            'is_not_empty' => ! empty($actual),
            'matches' => is_string($actual) && is_string($expected) && (bool) preg_match($expected, $actual),
            default => false,
        };
    }

    protected function looseEquals(mixed $actual, mixed $expected): bool
    {
        if (is_bool($actual)) {
            return $actual === filter_var($expected, FILTER_VALIDATE_BOOLEAN);
        }

        return (string) $actual === (string) $expected;
    }

    protected function valueContains(mixed $actual, mixed $expected): bool
    {
        if (is_array($actual)) {
            return in_array($expected, $actual, false);
        }

        if (is_string($actual) && is_string($expected)) {
            return str_contains($actual, $expected);
        }

        return false;
    }

    /**
     * Execute an ordered list of actions sequentially.
     */
    public function executeActions(Workflow $workflow, Ticket $ticket, array $actions): void
    {
        foreach ($actions as $index => $action) {
            if (($action['type'] ?? '') === 'delay') {
                $minutes = $action['value']['minutes'] ?? 0;
                $remaining = array_slice($actions, $index + 1);

                DelayedAction::create([
                    'workflow_id' => $workflow->id,
                    'ticket_id' => $ticket->id,
                    'action' => $action,
                    'remaining_actions' => $remaining,
                    'execute_at' => now()->addMinutes($minutes),
                ]);

                return;
            }

            $this->executeAction($workflow, $ticket, $action);
            $ticket->refresh();
        }
    }

    /**
     * Execute a single action on a ticket.
     */
    public function executeAction(Workflow $workflow, Ticket $ticket, array $action): void
    {
        $type = $action['type'] ?? '';
        $value = $action['value'] ?? null;

        match ($type) {
            'assign_agent' => $this->actionAssignAgent($ticket, $value),
            'change_status' => $this->actionChangeStatus($ticket, $value),
            'change_priority' => $this->actionChangePriority($ticket, $value),
            'add_tag' => $this->actionAddTag($ticket, $value),
            'remove_tag' => $this->actionRemoveTag($ticket, $value),
            'move_department' => $this->actionMoveDepartment($ticket, $value),
            'add_internal_note' => $this->actionAddInternalNote($ticket, $value, $workflow),
            'send_notification' => $this->actionSendNotification($ticket, $value, $workflow),
            'send_webhook' => $this->actionSendWebhook($ticket, $value),
            'apply_macro' => $this->actionApplyMacro($ticket, $value),
            'close_ticket' => $this->actionCloseTicket($ticket),
            'snooze_ticket' => $this->actionSnoozeTicket($ticket, $value),
            default => Log::warning("Escalated workflow: unknown action type '{$type}'", [
                'workflow_id' => $workflow->id,
                'ticket_id' => $ticket->id,
            ]),
        };
    }

    protected function actionAssignAgent(Ticket $ticket, mixed $value): void
    {
        if (is_array($value)) {
            $agentId = $value['agent_id'] ?? null;
            $strategy = $value['strategy'] ?? null;

            if ($strategy === 'least_busy') {
                $agentId = $this->findLeastBusyAgent();
            } elseif ($strategy === 'round_robin') {
                $agentId = $this->findRoundRobinAgent();
            }

            if ($agentId) {
                $ticket->update(['assigned_to' => $agentId]);
            }
        } else {
            $ticket->update(['assigned_to' => (int) $value]);
        }
    }

    protected function findLeastBusyAgent(): ?int
    {
        $userModel = Escalated::userModel();

        return $userModel::withCount(['tickets' => function ($q) {
            $q->whereNotIn('status', [TicketStatus::Resolved->value, TicketStatus::Closed->value]);
        }])
            ->orderBy('tickets_count')
            ->first()?->id;
    }

    protected function findRoundRobinAgent(): ?int
    {
        $userModel = Escalated::userModel();

        $lastAssigned = Ticket::whereNotNull('assigned_to')
            ->latest('updated_at')
            ->first()?->assigned_to;

        return $userModel::where('id', '>', $lastAssigned ?? 0)
            ->orderBy('id')
            ->first()?->id
            ?? $userModel::orderBy('id')->first()?->id;
    }

    protected function actionChangeStatus(Ticket $ticket, mixed $value): void
    {
        $status = TicketStatus::tryFrom($value);

        if ($status) {
            $ticket->update(['status' => $status]);
        }
    }

    protected function actionChangePriority(Ticket $ticket, mixed $value): void
    {
        $priority = TicketPriority::tryFrom($value);

        if ($priority) {
            $ticket->update(['priority' => $priority]);
        }
    }

    protected function actionAddTag(Ticket $ticket, mixed $value): void
    {
        $tag = Tag::firstOrCreate(['name' => $value], [
            'color' => '#6B7280',
        ]);

        $ticket->tags()->syncWithoutDetaching([$tag->id]);
    }

    protected function actionRemoveTag(Ticket $ticket, mixed $value): void
    {
        $tag = Tag::where('name', $value)->first();

        if ($tag) {
            $ticket->tags()->detach($tag->id);
        }
    }

    protected function actionMoveDepartment(Ticket $ticket, mixed $value): void
    {
        $departmentId = is_array($value) ? ($value['department_id'] ?? null) : $value;

        if ($departmentId) {
            $ticket->update(['department_id' => (int) $departmentId]);
        }
    }

    protected function actionAddInternalNote(Ticket $ticket, mixed $value, Workflow $workflow): void
    {
        $body = is_string($value) ? $value : ($value['body'] ?? '');

        $ticket->replies()->create([
            'body' => $body,
            'is_internal_note' => true,
            'is_pinned' => false,
            'metadata' => ['system_note' => true, 'workflow_id' => $workflow->id],
        ]);
    }

    protected function actionSendNotification(Ticket $ticket, mixed $value, Workflow $workflow): void
    {
        Log::info('Escalated workflow notification', [
            'workflow_id' => $workflow->id,
            'ticket_id' => $ticket->id,
            'notification' => $value,
        ]);
    }

    protected function actionSendWebhook(Ticket $ticket, mixed $value): void
    {
        if (! is_array($value)) {
            return;
        }

        $url = $value['url'] ?? null;

        if (! $url) {
            return;
        }

        $payload = $value['payload'] ?? [];
        $payload = $this->interpolateVariables($payload, $ticket);

        try {
            Http::timeout(10)->post($url, $payload);
        } catch (\Throwable $e) {
            Log::warning('Escalated workflow webhook failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Interpolate template variables in a payload.
     */
    public function interpolateVariables(mixed $data, Ticket $ticket): mixed
    {
        if (is_string($data)) {
            return preg_replace_callback('/\{\{(\w+)\.(\w+)\}\}/', function ($matches) use ($ticket) {
                $entity = $matches[1];
                $field = $matches[2];

                return match ($entity) {
                    'ticket' => match ($field) {
                        'reference' => $ticket->reference ?? '',
                        'subject' => $ticket->subject ?? '',
                        'status' => $ticket->status instanceof TicketStatus ? $ticket->status->value : ($ticket->status ?? ''),
                        'priority' => $ticket->priority instanceof TicketPriority ? $ticket->priority->value : ($ticket->priority ?? ''),
                        'id' => (string) $ticket->id,
                        default => '',
                    },
                    'agent' => match ($field) {
                        'name' => $ticket->assignee?->name ?? '',
                        'email' => $ticket->assignee?->email ?? '',
                        'id' => (string) ($ticket->assigned_to ?? ''),
                        default => '',
                    },
                    'department' => match ($field) {
                        'name' => $ticket->department?->name ?? '',
                        'id' => (string) ($ticket->department_id ?? ''),
                        default => '',
                    },
                    default => $matches[0],
                };
            }, $data);
        }

        if (is_array($data)) {
            return array_map(fn ($item) => $this->interpolateVariables($item, $ticket), $data);
        }

        return $data;
    }

    protected function actionApplyMacro(Ticket $ticket, mixed $value): void
    {
        $macroId = is_array($value) ? ($value['macro_id'] ?? null) : $value;

        if (! $macroId) {
            return;
        }

        $macro = Macro::find($macroId);

        if (! $macro) {
            return;
        }

        foreach ($macro->actions as $action) {
            $type = $action['type'] ?? null;
            $actionValue = $action['value'] ?? null;

            match ($type) {
                'status' => $ticket->update(['status' => TicketStatus::tryFrom($actionValue) ?? $ticket->status]),
                'priority' => $ticket->update(['priority' => TicketPriority::tryFrom($actionValue) ?? $ticket->priority]),
                'assign' => $ticket->update(['assigned_to' => (int) $actionValue]),
                'tags' => $ticket->tags()->syncWithoutDetaching(
                    Tag::whereIn('name', (array) $actionValue)->pluck('id')->toArray()
                ),
                'department' => $ticket->update(['department_id' => (int) $actionValue]),
                default => null,
            };

            $ticket->refresh();
        }
    }

    protected function actionCloseTicket(Ticket $ticket): void
    {
        $ticket->update([
            'status' => TicketStatus::Closed,
            'closed_at' => now(),
        ]);

        DelayedAction::where('ticket_id', $ticket->id)
            ->pending()
            ->update(['cancelled' => true]);
    }

    protected function actionSnoozeTicket(Ticket $ticket, mixed $value): void
    {
        $hours = is_array($value) ? ($value['hours'] ?? 1) : (int) $value;

        $ticket->update([
            'snoozed_until' => now()->addHours($hours),
        ]);
    }

    /**
     * Dry-run a workflow against a ticket.
     */
    public function dryRun(Workflow $workflow, Ticket $ticket): array
    {
        $matched = $this->evaluateConditions($workflow->conditions ?? [], $ticket);

        return [
            'conditions_matched' => $matched,
            'condition_details' => $this->evaluateConditionDetails($workflow->conditions ?? [], $ticket),
            'actions' => $matched ? ($workflow->actions ?? []) : [],
        ];
    }

    protected function evaluateConditionDetails(array $conditions, Ticket $ticket): array
    {
        $rules = $conditions['rules'] ?? [];
        $details = [];

        foreach ($rules as $rule) {
            $field = $rule['field'] ?? '';
            $actual = $this->resolveFieldValue($field, $ticket);
            $passed = $this->evaluateRule($rule, $ticket);

            $details[] = [
                'field' => $field,
                'operator' => $rule['operator'] ?? 'equals',
                'expected' => $rule['value'] ?? null,
                'actual' => $actual,
                'passed' => $passed,
            ];
        }

        return $details;
    }
}
