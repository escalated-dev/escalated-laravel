<?php

namespace Escalated\Laravel\Services;

use Carbon\Carbon;
use Escalated\Laravel\Escalated;
use Escalated\Laravel\Models\SatisfactionRating;
use Escalated\Laravel\Models\SlaPolicy;
use Escalated\Laravel\Models\Ticket;
use Illuminate\Support\Facades\DB;

class ReportingService
{
    /**
     * Get daily ticket counts between dates.
     */
    public function getTicketVolumeByDate(Carbon $startDate, Carbon $endDate): array
    {
        $driver = DB::connection()->getDriverName();
        $dateExpr = $driver === 'sqlite' ? 'date(created_at)' : 'DATE(created_at)';

        return Ticket::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw("{$dateExpr} as date, count(*) as count")
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => ['label' => $row->date, 'value' => $row->count])
            ->toArray();
    }

    /**
     * Get ticket count per status.
     */
    public function getTicketsByStatus(): array
    {
        return Ticket::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get()
            ->map(fn ($row) => ['label' => $row->status, 'value' => $row->count])
            ->toArray();
    }

    /**
     * Get ticket count per priority.
     */
    public function getTicketsByPriority(): array
    {
        return Ticket::select('priority', DB::raw('count(*) as count'))
            ->groupBy('priority')
            ->get()
            ->map(fn ($row) => ['label' => $row->priority, 'value' => $row->count])
            ->toArray();
    }

    /**
     * Get average first response time in hours.
     */
    public function getAverageResponseTime(Carbon $startDate, Carbon $endDate): float
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $raw = 'AVG((julianday(first_response_at) - julianday(created_at)) * 24) as avg_hours';
        } else {
            $raw = 'AVG(TIMESTAMPDIFF(HOUR, created_at, first_response_at)) as avg_hours';
        }

        return round((float) (Ticket::whereNotNull('first_response_at')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw($raw)
            ->value('avg_hours') ?? 0), 1);
    }

    /**
     * Get average resolution time in hours.
     */
    public function getAverageResolutionTime(Carbon $startDate, Carbon $endDate): float
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $raw = 'AVG((julianday(resolved_at) - julianday(created_at)) * 24) as avg_hours';
        } else {
            $raw = 'AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) as avg_hours';
        }

        return round((float) (Ticket::whereNotNull('resolved_at')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw($raw)
            ->value('avg_hours') ?? 0), 1);
    }

    /**
     * Get per-agent performance metrics.
     */
    public function getAgentPerformance(Carbon $startDate, Carbon $endDate): array
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $avgResponseRaw = 'AVG((julianday(first_response_at) - julianday(created_at)) * 24)';
            $avgResolutionRaw = 'AVG((julianday(resolved_at) - julianday(created_at)) * 24)';
        } else {
            $avgResponseRaw = 'AVG(TIMESTAMPDIFF(HOUR, created_at, first_response_at))';
            $avgResolutionRaw = 'AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at))';
        }

        $userModel = Escalated::newUserModel();
        $usersTable = $userModel->getTable();
        $ticketsTable = Escalated::table('tickets');

        return DB::table($ticketsTable)
            ->join($usersTable, "{$ticketsTable}.assigned_to", '=', "{$usersTable}.id")
            ->whereBetween("{$ticketsTable}.created_at", [$startDate, $endDate])
            ->whereNotNull("{$ticketsTable}.assigned_to")
            ->groupBy("{$ticketsTable}.assigned_to", "{$usersTable}.name")
            ->select([
                "{$ticketsTable}.assigned_to as agent_id",
                "{$usersTable}.name as agent_name",
                DB::raw('count(*) as total_tickets'),
                DB::raw("SUM(CASE WHEN {$ticketsTable}.resolved_at IS NOT NULL THEN 1 ELSE 0 END) as resolved_tickets"),
                DB::raw("ROUND({$avgResponseRaw}, 1) as avg_response_hours"),
                DB::raw("ROUND({$avgResolutionRaw}, 1) as avg_resolution_hours"),
            ])
            ->get()
            ->toArray();
    }

    /**
     * Get SLA compliance rate as a percentage.
     */
    public function getSlaComplianceRate(Carbon $startDate, Carbon $endDate): float
    {
        $total = Ticket::whereBetween('created_at', [$startDate, $endDate])
            ->whereNotNull('sla_policy_id')
            ->count();

        if ($total === 0) {
            return 100.0;
        }

        $breached = Ticket::whereBetween('created_at', [$startDate, $endDate])
            ->whereNotNull('sla_policy_id')
            ->where(function ($q) {
                $q->where('sla_first_response_breached', true)
                    ->orWhere('sla_resolution_breached', true);
            })
            ->count();

        return round((($total - $breached) / $total) * 100, 1);
    }

    /**
     * Get average satisfaction rating.
     */
    public function getCsatAverage(Carbon $startDate, Carbon $endDate): float
    {
        return round((float) (SatisfactionRating::whereBetween('created_at', [$startDate, $endDate])
            ->avg('rating') ?? 0), 1);
    }

    /**
     * Get detailed agent metrics for a specific agent.
     */
    public function getAgentMetrics(int $agentId, Carbon $startDate, Carbon $endDate): array
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $avgResponseRaw = 'AVG((julianday(first_response_at) - julianday(created_at)) * 24)';
            $avgResolutionRaw = 'AVG((julianday(resolved_at) - julianday(created_at)) * 24)';
        } else {
            $avgResponseRaw = 'AVG(TIMESTAMPDIFF(HOUR, created_at, first_response_at))';
            $avgResolutionRaw = 'AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at))';
        }

        $tickets = Ticket::where('assigned_to', $agentId)
            ->whereBetween('created_at', [$startDate, $endDate]);

        $totalTickets = (clone $tickets)->count();
        $resolvedTickets = (clone $tickets)->whereNotNull('resolved_at')->count();
        $openTickets = (clone $tickets)->whereNull('resolved_at')->count();

        $avgResponseHours = round((float) ((clone $tickets)
            ->whereNotNull('first_response_at')
            ->selectRaw("{$avgResponseRaw} as val")
            ->value('val') ?? 0), 1);

        $avgResolutionHours = round((float) ((clone $tickets)
            ->whereNotNull('resolved_at')
            ->selectRaw("{$avgResolutionRaw} as val")
            ->value('val') ?? 0), 1);

        // CSAT for this agent's tickets
        $ticketIds = (clone $tickets)->pluck('id');
        $csatAvg = round((float) (SatisfactionRating::whereIn('ticket_id', $ticketIds)
            ->avg('rating') ?? 0), 1);

        return [
            'total_tickets' => $totalTickets,
            'resolved_tickets' => $resolvedTickets,
            'open_tickets' => $openTickets,
            'avg_response_hours' => $avgResponseHours,
            'avg_resolution_hours' => $avgResolutionHours,
            'csat_average' => $csatAvg,
        ];
    }

    /**
     * Get SLA breach details.
     */
    public function getSlaBreachDetails(Carbon $startDate, Carbon $endDate): array
    {
        return Ticket::whereBetween('created_at', [$startDate, $endDate])
            ->where(function ($q) {
                $q->where('sla_first_response_breached', true)
                    ->orWhere('sla_resolution_breached', true);
            })
            ->with('assignee', 'slaPolicy')
            ->select(['id', 'reference', 'subject', 'assigned_to', 'sla_policy_id',
                'sla_first_response_breached', 'sla_resolution_breached',
                'first_response_at', 'resolved_at', 'created_at'])
            ->latest()
            ->limit(100)
            ->get()
            ->toArray();
    }

    /**
     * Get SLA compliance broken down by policy.
     */
    public function getSlaComplianceByPolicy(Carbon $startDate, Carbon $endDate): array
    {
        $policies = SlaPolicy::active()->get();
        $result = [];

        foreach ($policies as $policy) {
            $total = Ticket::whereBetween('created_at', [$startDate, $endDate])
                ->where('sla_policy_id', $policy->id)
                ->count();

            $breached = Ticket::whereBetween('created_at', [$startDate, $endDate])
                ->where('sla_policy_id', $policy->id)
                ->where(function ($q) {
                    $q->where('sla_first_response_breached', true)
                        ->orWhere('sla_resolution_breached', true);
                })
                ->count();

            $result[] = [
                'policy_name' => $policy->name,
                'total' => $total,
                'met' => $total - $breached,
                'breached' => $breached,
            ];
        }

        return $result;
    }

    /**
     * Get CSAT metrics with breakdown by agent.
     */
    public function getCsatByAgent(Carbon $startDate, Carbon $endDate): array
    {
        $userModel = Escalated::newUserModel();
        $usersTable = $userModel->getTable();
        $ticketsTable = Escalated::table('tickets');
        $ratingsTable = Escalated::table('satisfaction_ratings');

        return DB::table($ratingsTable)
            ->join($ticketsTable, "{$ratingsTable}.ticket_id", '=', "{$ticketsTable}.id")
            ->join($usersTable, "{$ticketsTable}.assigned_to", '=', "{$usersTable}.id")
            ->whereBetween("{$ratingsTable}.created_at", [$startDate, $endDate])
            ->groupBy("{$ticketsTable}.assigned_to", "{$usersTable}.name")
            ->select([
                "{$ticketsTable}.assigned_to as agent_id",
                "{$usersTable}.name as agent_name",
                DB::raw("ROUND(AVG({$ratingsTable}.rating), 1) as avg_rating"),
                DB::raw('COUNT(*) as total_ratings'),
            ])
            ->get()
            ->toArray();
    }

    /**
     * Get CSAT over time (daily averages).
     */
    public function getCsatOverTime(Carbon $startDate, Carbon $endDate): array
    {
        $driver = DB::connection()->getDriverName();
        $dateExpr = $driver === 'sqlite' ? 'date(created_at)' : 'DATE(created_at)';

        return SatisfactionRating::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw("{$dateExpr} as date, ROUND(AVG(rating), 1) as avg_rating, COUNT(*) as count")
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => ['label' => $row->date, 'value' => $row->avg_rating])
            ->toArray();
    }
}
