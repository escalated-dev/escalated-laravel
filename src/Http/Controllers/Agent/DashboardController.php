<?php

namespace Escalated\Laravel\Http\Controllers\Agent;

use Escalated\Laravel\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $userId = $request->user()->getKey();

        return Inertia::render('Escalated/Agent/Dashboard', [
            'stats' => [
                'open' => Ticket::open()->count(),
                'my_assigned' => Ticket::assignedTo($userId)->open()->count(),
                'unassigned' => Ticket::unassigned()->open()->count(),
                'sla_breached' => Ticket::open()->breachedSla()->count(),
                'resolved_today' => Ticket::where('resolved_at', '>=', now()->startOfDay())->count(),
            ],
            'recentTickets' => Ticket::with(['requester', 'assignee', 'department', 'latestReply.author'])
                ->latest()
                ->take(10)
                ->get(),
            'needsAttention' => [
                'sla_breaching' => Ticket::open()->breachedSla()->with(['requester', 'assignee'])->take(5)->get(),
                'unassigned_urgent' => Ticket::unassigned()->open()
                    ->whereIn('priority', ['urgent', 'critical'])
                    ->with(['requester'])
                    ->take(5)
                    ->get(),
            ],
            'myPerformance' => [
                'resolved_this_week' => Ticket::assignedTo($userId)
                    ->where('resolved_at', '>=', now()->startOfWeek())
                    ->count(),
            ],
        ]);
    }
}
