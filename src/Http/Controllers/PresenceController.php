<?php

namespace Escalated\Laravel\Http\Controllers;

use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

class PresenceController extends Controller
{
    /**
     * Record that an agent is actively typing on a ticket.
     * Typing state has a 10-second TTL (short-lived).
     */
    public function typing(Request $request, Ticket $ticket): JsonResponse
    {
        Gate::forUser($request->user())->authorize('addNote', $ticket);
        $userId = $request->user()->getKey();
        $userName = $request->user()->name;
        $typingKey = app(TenantContext::class)->cacheKey("escalated.typing.{$ticket->id}.{$userId}");

        Cache::put($typingKey, [
            'id' => $userId,
            'name' => $userName,
        ], 10); // 10 second TTL

        // Track typing user IDs
        $typingListKey = app(TenantContext::class)->cacheKey("escalated.typing_list.{$ticket->id}");
        $typingIds = Cache::get($typingListKey, []);
        if (! in_array($userId, $typingIds)) {
            $typingIds[] = $userId;
        }
        Cache::put($typingListKey, $typingIds, 30);

        // Build list of current typers (excluding the requester)
        $typers = [];
        $activeIds = [];
        foreach ($typingIds as $uid) {
            if ($uid !== $userId && Cache::has(app(TenantContext::class)->cacheKey("escalated.typing.{$ticket->id}.{$uid}"))) {
                $typers[] = Cache::get(app(TenantContext::class)->cacheKey("escalated.typing.{$ticket->id}.{$uid}"));
                $activeIds[] = $uid;
            }
            if ($uid === $userId) {
                $activeIds[] = $uid;
            }
        }

        // Clean stale typing IDs
        Cache::put($typingListKey, $activeIds, 30);

        // Also return viewers from the presence system
        $viewers = [];
        $presenceList = Cache::get(app(TenantContext::class)->cacheKey("escalated.presence_list.{$ticket->id}"), []);
        foreach ($presenceList as $uid) {
            if ($uid !== $userId && Cache::has(app(TenantContext::class)->cacheKey("escalated.presence.{$ticket->id}.{$uid}"))) {
                $viewers[] = Cache::get(app(TenantContext::class)->cacheKey("escalated.presence.{$ticket->id}.{$uid}"));
            }
        }

        return response()->json([
            'viewers' => $viewers,
            'typers' => $typers,
        ]);
    }
}
