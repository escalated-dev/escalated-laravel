<?php

namespace Escalated\Laravel\Http\Controllers\Agent;

use Escalated\Laravel\Services\AgentNotificationFeed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * The agent's in-app notifications (ADR 2026-11-05-agent-panel-data,
 * section 4). JSON only; 404 while in-app delivery is off.
 */
class NotificationController extends Controller
{
    public function __construct(private AgentNotificationFeed $feed) {}

    public function index(Request $request): JsonResponse
    {
        $user = $this->user($request);

        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.AgentNotificationFeed::MAX_PER_PAGE],
            'unread' => ['sometimes', 'boolean'],
        ]);

        $query = $this->feed->query($user)->latest('created_at')->latest('id');
        if ($request->boolean('unread')) {
            $query->whereNull('read_at');
        }

        $page = $query->paginate((int) ($validated['per_page'] ?? AgentNotificationFeed::PER_PAGE));

        return response()->json([
            'data' => $this->feed->present($page->getCollection()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
            'unread_count' => $this->feed->unreadCount($user),
        ]);
    }

    public function read(Request $request, string $notification): JsonResponse
    {
        $user = $this->user($request);

        $this->feed->query($user)->whereKey($notification)->firstOrFail()->markAsRead();

        return response()->json(['unread_count' => $this->feed->unreadCount($user)]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $user = $this->user($request);

        $this->feed->query($user)->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['unread_count' => 0]);
    }

    private function user(Request $request): mixed
    {
        $user = $request->user();
        abort_unless($this->feed->available($user), 404);

        return $user;
    }
}
