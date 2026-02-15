<?php

namespace Escalated\Laravel\Http\Controllers\Api;

use Escalated\Laravel\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $userId = $request->user()->getKey();

        return response()->json([
            'stats' => [
                'open' => Ticket::open()->count(),
                'my_assigned' => Ticket::assignedTo($userId)->open()->count(),
                'unassigned' => Ticket::unassigned()->open()->count(),
                'sla_breached' => Ticket::open()->breachedSla()->count(),
                'resolved_today' => Ticket::where('resolved_at', '>=', now()->startOfDay())->count(),
            ],
            'recent_tickets' => Ticket::with(['requester', 'assignee', 'department'])
                ->latest()
                ->take(10)
                ->get()
                ->map(fn ($t) => [
                    'id' => $t->id,
                    'reference' => $t->reference,
                    'subject' => $t->subject,
                    'status' => $t->status->value,
                    'priority' => $t->priority->value,
                    'requester_name' => $t->requester_name,
                    'assignee_name' => $t->assignee?->name,
                    'created_at' => $t->created_at->toIso8601String(),
                ]),
            'needs_attention' => [
                'sla_breaching' => Ticket::open()->breachedSla()->with(['requester', 'assignee'])->take(5)->get()->map(fn ($t) => [
                    'reference' => $t->reference,
                    'subject' => $t->subject,
                    'priority' => $t->priority->value,
                    'requester_name' => $t->requester_name,
                ]),
                'unassigned_urgent' => Ticket::unassigned()->open()
                    ->whereIn('priority', ['urgent', 'critical'])
                    ->with(['requester'])
                    ->take(5)
                    ->get()
                    ->map(fn ($t) => [
                        'reference' => $t->reference,
                        'subject' => $t->subject,
                        'priority' => $t->priority->value,
                        'requester_name' => $t->requester_name,
                    ]),
            ],
            'my_performance' => [
                'resolved_this_week' => Ticket::assignedTo($userId)
                    ->where('resolved_at', '>=', now()->startOfWeek())
                    ->count(),
            ],
        ]);
    }
}
