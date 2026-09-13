<?php

namespace Escalated\Laravel\Http\Controllers\Admin;

use Closure;
use Escalated\Laravel\Contracts\EscalatedUiRenderer;
use Escalated\Laravel\Models\Webhook;
use Escalated\Laravel\Models\WebhookDelivery;
use Escalated\Laravel\Services\WebhookDispatcher;
use Escalated\Laravel\Support\OutboundUrlGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'url' => ['bail', 'required', 'url', 'max:500', $this->publicUrl()],
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
        // Show a subscription saved under a legacy name as its current name,
        // so the form ticks it and saving keeps it.
        $webhook->events = $webhook->subscribedEvents();

        return $this->renderer->render('Escalated/Admin/Webhooks/Form', [
            'webhook' => $webhook,
            'availableEvents' => $this->availableEvents(),
        ]);
    }

    public function update(Request $request, Webhook $webhook): RedirectResponse
    {
        $request->validate([
            'url' => ['bail', 'required', 'url', 'max:500', $this->publicUrl()],
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

    /**
     * Refuses a URL the dispatcher would refuse to send to, so the admin
     * learns about it when saving rather than from a log of failed deliveries.
     */
    protected function publicUrl(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || ! app(OutboundUrlGuard::class)->allows($value)) {
                $fail('The webhook URL must use http or https and resolve to a public address.');
            }
        };
    }

    /**
     * The names DispatchWebhook sends events under. Anything else here is a
     * checkbox that never fires.
     */
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
            'note.created',
            'sla.breached',
            'sla.warning',
            'ticket.tag_added',
            'ticket.tag_removed',
        ];
    }
}
