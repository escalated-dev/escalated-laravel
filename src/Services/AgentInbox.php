<?php

namespace Escalated\Laravel\Services;

use Escalated\Laravel\Models\SavedView;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Support\TicketListFilters;
use Escalated\Laravel\Tenancy\TenantContext;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * The agent panel's `inbox` and `queue` props (shared frontend
 * docs/agent-panel.md).
 *
 * Every number is a count over the same filters the ticket list applies,
 * through Ticket's global visibility scope, so it is what the agent would see
 * by opening that list. Rail counts (`counts`, `views`) are of unresolved,
 * unsnoozed tickets; `stats` are for the current queue.
 */
class AgentInbox
{
    public const SESSION_KEY = 'escalated.agent_inbox.queue';

    /** Saved views beyond this many are not listed, so the counts stay bounded. */
    public const MAX_SAVED_VIEWS = 50;

    /** Query keys that pick a page or an order, never which tickets are in a queue. */
    private const PRESENTATION_KEYS = ['page', 'per_page', 'view'];

    /** @var array<string, Collection<int, SavedView>> */
    private array $savedViews = [];

    /** Translate `assigned=me` and `assigned_to=me` into the agent's id. */
    public function normaliseFilters(array $filters, Authenticatable $agent): array
    {
        if (($filters['assigned'] ?? null) === 'me' || ($filters['assigned_to'] ?? null) === 'me') {
            $filters['assigned_to'] = $agent->getAuthIdentifier();
        }
        unset($filters['assigned']);

        return $filters;
    }

    /**
     * The queue a list request shows: `['key', 'title', 'filters']`, where
     * `filters` are the queue's own (what its counts and stats are over).
     */
    public function queueFor(array $filters, Authenticatable $agent): array
    {
        $views = $this->views($agent);

        if (isset($filters['view']) && $filters['view'] !== '') {
            $view = $views->first(fn (array $view) => (string) $view['id'] === (string) $filters['view']);
            if ($view) {
                return $this->viewQueue($view);
            }
        }

        $selection = $this->comparable(array_diff_key($filters, array_flip(self::PRESENTATION_KEYS)));

        if ($selection !== []) {
            $view = $views->first(fn (array $view) => $view['filters'] !== [] && $this->comparable($view['filters']) === $selection);
            if ($view) {
                return $this->viewQueue($view);
            }
        }

        $me = (string) $agent->getAuthIdentifier();

        if (isset($filters['assigned_to']) && (string) $filters['assigned_to'] === $me) {
            return $this->workspaceQueue('mine', ['assigned_to' => $agent->getAuthIdentifier()]);
        }

        if (! empty($filters['unassigned']) && filter_var($filters['unassigned'], FILTER_VALIDATE_BOOLEAN)) {
            return $this->workspaceQueue('unassigned', ['unassigned' => 1]);
        }

        return $this->workspaceQueue('all', []);
    }

    /** The filters the list applies: the request's, over a view picked by id. */
    public function listFilters(array $filters, array $queue): array
    {
        if (isset($filters['view']) && str_starts_with($queue['key'], 'view:')) {
            return array_merge($queue['filters'], array_diff_key($filters, ['view' => true]));
        }

        return $filters;
    }

    /** Remember the queue for the ticket page, which is opened from the list. */
    public function remember(Request $request, array $queue): void
    {
        if ($request->hasSession()) {
            $request->session()->put(self::SESSION_KEY, $queue);
        }
    }

    /** The queue last shown in the list, or All tickets. */
    public function recalled(Request $request, Authenticatable $agent): array
    {
        $queue = $request->hasSession() ? $request->session()->get(self::SESSION_KEY) : null;

        if (! is_array($queue) || ! isset($queue['key'], $queue['filters']) || ! is_array($queue['filters'])) {
            return $this->workspaceQueue('all', []);
        }

        if (str_starts_with($queue['key'], 'view:')) {
            // The view may have been deleted or unshared since.
            $id = substr($queue['key'], 5);
            $view = $this->views($agent)->first(fn (array $view) => (string) $view['id'] === $id);

            return $view ? $this->viewQueue($view) : $this->workspaceQueue('all', []);
        }

        return $this->workspaceQueue($queue['key'], $queue['filters']);
    }

    /** The `queue` prop. */
    public function queueProp(array $queue): array
    {
        return ['key' => $queue['key'], 'title' => $queue['title']];
    }

    /**
     * `['inbox' => ...]`, or nothing in cloud mode: there the tickets live in
     * the hosted service, and counting the local table would show zeroes.
     */
    public function inboxProp(Authenticatable $agent, array $queue): array
    {
        return config('escalated.mode', 'self-hosted') === 'cloud' ? [] : ['inbox' => $this->summary($agent, $queue)];
    }

    /** The `inbox` prop for this agent and queue. */
    public function summary(Authenticatable $agent, array $queue): array
    {
        $seconds = (int) config('escalated.agent_inbox.cache_seconds', 10);

        if ($seconds <= 0) {
            return $this->build($agent, $queue);
        }

        $key = app(TenantContext::class)->cacheKey(sprintf(
            'escalated.agent_inbox.%s.%s.%s',
            $agent->getAuthIdentifier(),
            app()->getLocale(),
            sha1(json_encode([$queue['key'], $this->comparable($queue['filters'])]))
        ));

        return Cache::remember($key, $seconds, fn () => $this->build($agent, $queue));
    }

    private function build(Authenticatable $agent, array $queue): array
    {
        $me = $agent->getAuthIdentifier();
        $base = $queue['filters'];

        return [
            'counts' => [
                'mine' => $this->countOpen(['assigned_to' => $me]),
                'unassigned' => $this->countOpen(['unassigned' => 1]),
                'all' => $this->countOpen([]),
            ],
            'views' => $this->views($agent)
                ->map(fn (array $view) => [
                    'id' => $view['id'],
                    'name' => $view['name'],
                    'count' => $this->countOpen($view['filters']),
                    'filters' => $view['filters'],
                ])
                ->values()
                ->all(),
            // Each stat is the list the tile opens: the queue plus the tile's filters.
            'stats' => [
                'open' => $this->count(array_merge($base, ['status' => 'open'])),
                'urgent' => $this->count(array_merge($base, ['priority' => 'urgent', 'open' => 1])),
                'sla_at_risk' => $this->count(array_merge($base, ['sla_at_risk' => 1])),
                'resolved_today' => $this->count(array_merge($base, ['status' => 'resolved', 'resolved_today' => 1])),
            ],
        ];
    }

    /**
     * Built-in views, then the agent's own and shared saved views.
     *
     * @return Collection<int, array{id: int|string, name: string, filters: array}>
     */
    public function views(Authenticatable $agent): Collection
    {
        $me = $agent->getAuthIdentifier();

        $builtIn = collect([
            ['id' => 'urgent', 'filters' => ['priority' => 'urgent', 'open' => 1]],
            ['id' => 'sla_at_risk', 'filters' => ['sla_at_risk' => 1]],
            ['id' => 'waiting_on_me', 'filters' => ['assigned_to' => $me, 'status' => 'waiting_on_agent']],
            ['id' => 'waiting_on_customer', 'filters' => ['assigned_to' => $me, 'status' => 'waiting_on_customer']],
            ['id' => 'recently_updated', 'filters' => ['open' => 1, 'updated_within_hours' => 24, 'sort_by' => 'updated_at']],
        ])->map(fn (array $view) => [
            'id' => $view['id'],
            'name' => __('escalated::messages.agent_inbox.views.'.$view['id']),
            'filters' => $view['filters'],
        ]);

        $saved = $this->savedViews[(string) $me] ??= SavedView::forUser($me)
            ->orderBy('position')
            ->orderBy('id')
            ->limit(self::MAX_SAVED_VIEWS)
            ->get(['id', 'name', 'filters', 'position']);

        return $builtIn->concat($saved->map(fn (SavedView $view) => [
            'id' => $view->id,
            'name' => $view->name,
            'filters' => $this->normaliseFilters(is_array($view->filters) ? $view->filters : [], $agent),
        ]));
    }

    private function viewQueue(array $view): array
    {
        return ['key' => 'view:'.$view['id'], 'title' => $view['name'], 'filters' => $view['filters']];
    }

    private function workspaceQueue(string $key, array $filters): array
    {
        if (! in_array($key, ['mine', 'unassigned', 'all'], true)) {
            $key = 'all';
            $filters = [];
        }

        return ['key' => $key, 'title' => __('escalated::messages.agent_inbox.queues.'.$key), 'filters' => $filters];
    }

    /** Unresolved tickets, unless the filters ask for a status themselves. */
    private function countOpen(array $filters): int
    {
        if (empty($filters['status'])) {
            $filters['open'] = 1;
        }

        return $this->count($filters);
    }

    private function count(array $filters): int
    {
        return TicketListFilters::apply(Ticket::query(), $filters)->count();
    }

    /** Filters as strings, without empty values or order, for comparing a URL with a view. */
    private function comparable(array $filters): array
    {
        $clean = [];
        foreach ($filters as $key => $value) {
            if ($value === '' || $value === null || $value === false || in_array($key, self::PRESENTATION_KEYS, true)) {
                continue;
            }
            if (is_array($value)) {
                $value = array_map('strval', $value);
                sort($value);
                $clean[$key] = implode(',', $value);
            } else {
                $clean[$key] = (string) ($value === true ? 1 : $value);
            }
        }
        ksort($clean);

        return $clean;
    }
}
