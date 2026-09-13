<?php

namespace Escalated\Laravel\Http\Controllers\Admin;

use Escalated\Laravel\Contracts\EscalatedUiRenderer;
use Escalated\Laravel\Models\AuditLog;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Models\Workflow;
use Escalated\Laravel\Services\WorkflowEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

/**
 * The Workflows admin screen. What goes over the wire is fixed by
 * escalated-developer-context domain-model/workflow-admin-contract.md.
 */
class WorkflowController extends Controller
{
    /**
     * Action types create and update accept: the contract's core and optional
     * catalog, the legacy aliases stored by earlier Laravel workflows, and the
     * Laravel-only actions the engine still runs.
     */
    protected const ACCEPTED_ACTION_TYPES = [
        'change_status', 'change_priority', 'add_tag', 'remove_tag', 'set_department',
        'assign_agent', 'add_note', 'insert_canned_reply',
        'add_follower', 'delay', 'send_webhook',
        'move_department', 'add_internal_note',
        'send_notification', 'apply_macro', 'close_ticket', 'snooze_ticket',
    ];

    public function __construct(
        protected EscalatedUiRenderer $renderer,
    ) {}

    public function index(): mixed
    {
        $workflows = Workflow::orderBy('position')->get();

        return $this->renderer->render('Escalated/Admin/Workflows/Index', [
            'workflows' => $workflows,
        ]);
    }

    public function create(): mixed
    {
        return $this->renderer->render('Escalated/Admin/Workflows/Form', array_merge(
            ['workflow' => null],
            $this->formOptions(),
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate($this->validationRules());

        $workflow = Workflow::create([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'trigger_event' => $request->input('trigger_event'),
            'conditions' => $this->conditionsFrom($request),
            'actions' => $request->input('actions'),
            'is_active' => $request->boolean('is_active', true),
            'position' => (Workflow::max('position') ?? 0) + 1,
            'created_by' => $request->user()?->id,
        ]);

        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => 'workflow.created',
            'auditable_type' => $workflow->getMorphClass(),
            'auditable_id' => $workflow->id,
            'new_values' => ['name' => $workflow->name, 'trigger_event' => $workflow->trigger_event],
        ]);

        return redirect()->route('escalated.admin.workflows.index')
            ->with('success', 'Workflow created.');
    }

    public function edit(Workflow $workflow): mixed
    {
        return $this->renderer->render('Escalated/Admin/Workflows/Form', array_merge(
            ['workflow' => $workflow],
            $this->formOptions(),
        ));
    }

    public function update(Request $request, Workflow $workflow): RedirectResponse
    {
        $request->validate($this->validationRules());

        $oldValues = ['name' => $workflow->name, 'trigger_event' => $workflow->trigger_event];

        $workflow->update([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'trigger_event' => $request->input('trigger_event'),
            'conditions' => $this->conditionsFrom($request),
            'actions' => $request->input('actions'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => 'workflow.updated',
            'auditable_type' => $workflow->getMorphClass(),
            'auditable_id' => $workflow->id,
            'old_values' => $oldValues,
            'new_values' => ['name' => $workflow->name, 'trigger_event' => $workflow->trigger_event],
        ]);

        return redirect()->route('escalated.admin.workflows.index')
            ->with('success', 'Workflow updated.');
    }

    public function destroy(Request $request, Workflow $workflow): RedirectResponse
    {
        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => 'workflow.deleted',
            'auditable_type' => $workflow->getMorphClass(),
            'auditable_id' => $workflow->id,
            'old_values' => ['name' => $workflow->name, 'trigger_event' => $workflow->trigger_event],
        ]);

        $workflow->delete();

        return redirect()->route('escalated.admin.workflows.index')
            ->with('success', 'Workflow deleted.');
    }

    public function toggle(Workflow $workflow): RedirectResponse
    {
        $workflow->update(['is_active' => ! $workflow->is_active]);

        $state = $workflow->is_active ? 'enabled' : 'disabled';

        return redirect()->route('escalated.admin.workflows.index')
            ->with('success', "Workflow {$state}.");
    }

    /**
     * The contract sends `workflow_ids`; `ids` is the older name and still
     * works. An Inertia visit cannot consume JSON, so it gets a redirect.
     */
    public function reorder(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate([
            'workflow_ids' => 'required_without:ids|array',
            'workflow_ids.*' => 'integer',
            'ids' => 'required_without:workflow_ids|array',
            'ids.*' => 'integer',
        ]);

        $ids = $request->input('workflow_ids') ?? $request->input('ids');

        foreach (array_values($ids) as $position => $id) {
            Workflow::where('id', $id)->update(['position' => $position]);
        }

        if ($request->header('X-Inertia')) {
            return redirect()->route('escalated.admin.workflows.index');
        }

        return response()->json(['success' => true]);
    }

    public function logs(Workflow $workflow): mixed
    {
        $logs = $workflow->workflowLogs()
            ->with('workflow', 'ticket')
            ->latest()
            ->paginate(25);

        return $this->renderer->render('Escalated/Admin/Workflows/Logs', [
            'workflow' => $workflow,
            'logs' => $logs,
        ]);
    }

    public function test(Request $request, Workflow $workflow, WorkflowEngine $engine): JsonResponse
    {
        $request->validate([
            'ticket_id' => 'required|integer',
        ]);

        $ticket = Ticket::findOrFail($request->input('ticket_id'));
        $result = $engine->dryRun($workflow, $ticket);

        return response()->json($result);
    }

    protected function validationRules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'trigger_event' => 'required|string',
            'conditions' => 'nullable|array',
            'actions' => 'required|array|min:1',
            'actions.*.type' => ['required', 'string', Rule::in(self::ACCEPTED_ACTION_TYPES)],
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Omitted or empty conditions are stored as the contract's `{"all": []}`.
     */
    protected function conditionsFrom(Request $request): array
    {
        return $request->input('conditions') ?: ['all' => []];
    }

    /**
     * The Form page's option lists, as the contract's preferred `[{value, label}]`.
     */
    protected function formOptions(): array
    {
        return [
            'trigger_events' => $this->toOptions($this->availableTriggerEvents()),
            'action_types' => $this->toOptions($this->availableActionTypes()),
            'operators' => $this->toOptions($this->availableOperators()),
        ];
    }

    protected function toOptions(array $labelsByValue): array
    {
        return array_map(
            fn (string $value, string $label) => ['value' => $value, 'label' => $label],
            array_keys($labelsByValue),
            array_values($labelsByValue),
        );
    }

    /**
     * The events Listeners\ProcessWorkflows fires. The builder offers exactly
     * this list, so an event that is not fired must not appear here.
     */
    protected function availableTriggerEvents(): array
    {
        return [
            'ticket.created' => 'Ticket Created',
            'ticket.updated' => 'Ticket Updated',
            'ticket.replied' => 'Ticket Replied',
            'ticket.status_changed' => 'Ticket Status Changed',
            'ticket.assigned' => 'Ticket Assigned',
            'ticket.escalated' => 'Ticket Escalated',
            'sla.breached' => 'SLA Breached',
            'sla.warning' => 'SLA Warning',
            'chat.started' => 'Chat Started',
            'chat.ended' => 'Chat Ended',
        ];
    }

    /**
     * The contract's core catalog plus the optional actions WorkflowEngine
     * handles. Legacy aliases and Laravel-only actions stay accepted but are
     * not offered, so the builder only creates canonical workflows.
     */
    protected function availableActionTypes(): array
    {
        return [
            'change_status' => 'Change Status',
            'change_priority' => 'Change Priority',
            'add_tag' => 'Add Tag',
            'remove_tag' => 'Remove Tag',
            'set_department' => 'Set Department',
            'assign_agent' => 'Assign Agent',
            'add_note' => 'Add Internal Note',
            'insert_canned_reply' => 'Send Canned Reply',
            'add_follower' => 'Add Follower',
            'delay' => 'Delay (minutes)',
            'send_webhook' => 'Send Webhook',
        ];
    }

    /**
     * Operators WorkflowEngine::compareValues() evaluates against the scalar
     * value the builder sends.
     */
    protected function availableOperators(): array
    {
        return [
            'equals' => 'Equals',
            'not_equals' => 'Does Not Equal',
            'contains' => 'Contains',
            'not_contains' => 'Does Not Contain',
            'starts_with' => 'Starts With',
            'ends_with' => 'Ends With',
            'greater_than' => 'Greater Than',
            'less_than' => 'Less Than',
            'greater_or_equal' => 'Greater Than or Equal',
            'less_or_equal' => 'Less Than or Equal',
            'is_empty' => 'Is Empty',
            'is_not_empty' => 'Is Not Empty',
            'matches' => 'Matches Pattern',
        ];
    }
}
