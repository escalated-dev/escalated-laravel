<?php

namespace Escalated\Laravel\Http\Controllers\Admin;

use Escalated\Laravel\Models\Webhook;
use Escalated\Laravel\Models\WebhookDelivery;
use Escalated\Laravel\Services\WebhookDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Escalated\Laravel\Contracts\EscalatedUiRenderer;
use Illuminate\Routing\Controller;

class WebhookController extends Controller
{
    public function __construct(
        protected EscalatedUiRenderer $renderer,
    ) {}

    public function index(): mixed
    {
        $webhooks = Webhook::withCount('deliveries')
            ->with(['deliveries' => fn ($q) => $q->latest()->limit(1)])
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->renderer->render('Escalated/Admin/Webhooks/Index', [
            'webhooks' => $webhooks,
        ]);
    }

    public function create(): mixed
    {
        return $this->renderer->render('Escalated/Admin/Webhooks/Form', [
            'availableEvents' => $this->availableEvents(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'url' => 'required|url|max:500',
            'events' => 'required|array|min:1',
            'events.*' => 'string',
            'secret' => 'nullable|string|max:255',
            'active' => 'boolean',
        ]);

        Webhook::create($request->only(['url', 'events', 'secret', 'active']));

        return redirect()->route('escalated.admin.webhooks.index')
            ->with('success', 'Webhook created.');
    }

    public function edit(Webhook $webhook): mixed
    {
        return $this->renderer->render('Escalated/Admin/Webhooks/Form', [
            'webhook' => $webhook,
            'availableEvents' => $this->availableEvents(),
        ]);
    }

    public function update(Request $request, Webhook $webhook): RedirectResponse
    {
        $request->validate([
            'url' => 'required|url|max:500',
            'events' => 'required|array|min:1',
            'events.*' => 'string',
            'secret' => 'nullable|string|max:255',
            'active' => 'boolean',
        ]);

        $webhook->update($request->only(['url', 'events', 'secret', 'active']));

        return redirect()->route('escalated.admin.webhooks.index')
            ->with('success', 'Webhook updated.');
    }

    public function destroy(Webhook $webhook): RedirectResponse
    {
        $webhook->delete();

        return redirect()->route('escalated.admin.webhooks.index')
            ->with('success', 'Webhook deleted.');
    }

    public function deliveries(Webhook $webhook): mixed
    {
        $deliveries = $webhook->deliveries()
            ->latest()
            ->paginate(25);

        return $this->renderer->render('Escalated/Admin/Webhooks/DeliveryLog', [
            'webhook' => $webhook,
            'deliveries' => $deliveries,
        ]);
    }

    public function retry(WebhookDelivery $delivery, WebhookDispatcher $dispatcher): RedirectResponse
    {
        $dispatcher->retryDelivery($delivery);

        return back()->with('success', 'Webhook delivery retried.');
    }

    protected function availableEvents(): array
    {
        return [
            'ticket.created',
            'ticket.updated',
            'ticket.status_changed',
            'ticket.resolved',
            'ticket.closed',
            'ticket.reopened',
            'ticket.assigned',
            'ticket.unassigned',
            'ticket.escalated',
            'ticket.priority_changed',
            'ticket.department_changed',
            'reply.created',
            'internal_note.added',
            'sla.breached',
            'sla.warning',
            'tag.added',
            'tag.removed',
        ];
    }
}
