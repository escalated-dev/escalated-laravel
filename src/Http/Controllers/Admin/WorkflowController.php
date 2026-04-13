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

class WorkflowController extends Controller
{
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
        return $this->renderer->render('Escalated/Admin/Workflows/Form', [
            'triggerEvents' => $this->availableTriggerEvents(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'trigger_event' => 'required|string',
            'conditions' => 'required|array',
            'actions' => 'required|array|min:1',
            'actions.*.type' => 'required|string|in:assign_agent,change_status,change_priority,add_tag,remove_tag,move_department,add_internal_note,send_notification,send_webhook,apply_macro,close_ticket,snooze_ticket,delay',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $workflow = Workflow::create([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'trigger_event' => $request->input('trigger_event'),
            'conditions' => $request->input('conditions'),
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
        return $this->renderer->render('Escalated/Admin/Workflows/Form', [
            'workflow' => $workflow,
            'triggerEvents' => $this->availableTriggerEvents(),
        ]);
    }

    public function update(Request $request, Workflow $workflow): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'trigger_event' => 'required|string',
            'conditions' => 'required|array',
            'actions' => 'required|array|min:1',
            'actions.*.type' => 'required|string|in:assign_agent,change_status,change_priority,add_tag,remove_tag,move_department,add_internal_note,send_notification,send_webhook,apply_macro,close_ticket,snooze_ticket,delay',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $oldValues = ['name' => $workflow->name, 'trigger_event' => $workflow->trigger_event];

        $workflow->update([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'trigger_event' => $request->input('trigger_event'),
            'conditions' => $request->input('conditions'),
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

    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer',
        ]);

        foreach ($request->input('ids') as $position => $id) {
            Workflow::where('id', $id)->update(['position' => $position]);
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
}
