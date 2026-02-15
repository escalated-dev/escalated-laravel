<?php

namespace Escalated\Laravel\Http\Controllers\Admin;

use Escalated\Laravel\Models\SatisfactionRating;
use Escalated\Laravel\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $days = $request->integer('days', 30);
        $since = now()->subDays($days);

        return Inertia::render('Escalated/Admin/Reports', [
            'period_days' => $days,
            'total_tickets' => Ticket::where('created_at', '>=', $since)->count(),
            'resolved_tickets' => Ticket::whereNotNull('resolved_at')->where('resolved_at', '>=', $since)->count(),
            'avg_first_response_hours' => round($this->avgFirstResponseHours($since), 1),
            'sla_breach_count' => Ticket::where('created_at', '>=', $since)->breachedSla()->count(),
            'by_status' => Ticket::where('created_at', '>=', $since)
                ->select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status'),
            'by_priority' => Ticket::where('created_at', '>=', $since)
                ->select('priority', DB::raw('count(*) as count'))
                ->groupBy('priority')
                ->pluck('count', 'priority'),
            'csat' => $this->getCsatMetrics($since),
        ]);
    }

    protected function avgFirstResponseHours($since): float
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $raw = 'AVG((julianday(first_response_at) - julianday(created_at)) * 24) as avg_hours';
        } else {
            $raw = 'AVG(TIMESTAMPDIFF(HOUR, created_at, first_response_at)) as avg_hours';
        }

        return (float) (Ticket::whereNotNull('first_response_at')
            ->where('created_at', '>=', $since)
            ->selectRaw($raw)
            ->value('avg_hours') ?? 0);
    }

    protected function getCsatMetrics($since): array
    {
        $ratings = SatisfactionRating::where('created_at', '>=', $since);

        return [
            'average' => round((float) ($ratings->avg('rating') ?? 0), 1),
            'total' => $ratings->count(),
            'breakdown' => SatisfactionRating::where('created_at', '>=', $since)
                ->select('rating', DB::raw('count(*) as count'))
                ->groupBy('rating')
                ->pluck('count', 'rating'),
        ];
    }
}
