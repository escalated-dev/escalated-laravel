<?php

namespace Escalated\Laravel\Services;

use Carbon\Carbon;
use Escalated\Laravel\Escalated;
use Escalated\Laravel\Models\Department;
use Escalated\Laravel\Models\SatisfactionRating;
use Escalated\Laravel\Models\SlaPolicy;
use Escalated\Laravel\Models\Tag;
use Escalated\Laravel\Models\Ticket;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportingService
{
    /**
     * Get daily ticket counts between dates.
     */
    public function getTicketVolumeByDate(Carbon $startDate, Carbon $endDate): array
    {
        $dateExpr = $this->dateExpression('created_at');

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
        $raw = $this->avgHoursDiffExpression('created_at', 'first_response_at');

        return round((float) (Ticket::whereNotNull('first_response_at')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw("{$raw} as avg_hours")
            ->value('avg_hours') ?? 0), 1);
    }

    /**
     * Get average resolution time in hours.
     */
    public function getAverageResolutionTime(Carbon $startDate, Carbon $endDate): float
    {
        $raw = $this->avgHoursDiffExpression('created_at', 'resolved_at');

        return round((float) (Ticket::whereNotNull('resolved_at')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw("{$raw} as avg_hours")
            ->value('avg_hours') ?? 0), 1);
    }

    /**
     * Get per-agent performance metrics.
     */
    public function getAgentPerformance(Carbon $startDate, Carbon $endDate): array
    {
        $userModel = Escalated::newUserModel();
        $usersTable = $userModel->getTable();
        $ticketsTable = Escalated::table('tickets');

        $avgResponseRaw = $this->avgHoursDiffRaw("{$ticketsTable}.created_at", "{$ticketsTable}.first_response_at");
        $avgResolutionRaw = $this->avgHoursDiffRaw("{$ticketsTable}.created_at", "{$ticketsTable}.resolved_at");

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
        $avgResponseRaw = $this->avgHoursDiffExpression('created_at', 'first_response_at');
        $avgResolutionRaw = $this->avgHoursDiffExpression('created_at', 'resolved_at');

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
        $dateExpr = $this->dateExpression('created_at');

        return SatisfactionRating::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw("{$dateExpr} as date, ROUND(AVG(rating), 1) as avg_rating, COUNT(*) as count")
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => ['label' => $row->date, 'value' => $row->avg_rating])
            ->toArray();
    }

    // ──────────────────────────────────────────────────────────────────────
    // SLA Breach Trends
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Daily/weekly/monthly breach counts over time, split by first-response vs resolution breaches.
     */
    public function slaBreachTrends(int $days, ?string $groupBy = 'day'): array
    {
        $since = now()->subDays($days);
        $dateExpr = $this->groupByDateExpression('created_at', $groupBy);

        return Ticket::where('created_at', '>=', $since)
            ->where(function ($q) {
                $q->where('sla_first_response_breached', true)
                    ->orWhere('sla_resolution_breached', true);
            })
            ->selectRaw("{$dateExpr} as period")
            ->selectRaw('SUM(CASE WHEN sla_first_response_breached = 1 THEN 1 ELSE 0 END) as first_response_breaches')
            ->selectRaw('SUM(CASE WHEN sla_resolution_breached = 1 THEN 1 ELSE 0 END) as resolution_breaches')
            ->selectRaw('COUNT(*) as total_breaches')
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->map(fn ($row) => [
                'period' => $row->period,
                'first_response_breaches' => (int) $row->first_response_breaches,
                'resolution_breaches' => (int) $row->resolution_breaches,
                'total_breaches' => (int) $row->total_breaches,
            ])
            ->toArray();
    }

    /**
     * Breach rate per department.
     */
    public function slaBreachByDepartment(int $days): array
    {
        $since = now()->subDays($days);
        $ticketsTable = Escalated::table('tickets');
        $departmentsTable = Escalated::table('departments');

        return DB::table($ticketsTable)
            ->leftJoin($departmentsTable, "{$ticketsTable}.department_id", '=', "{$departmentsTable}.id")
            ->where("{$ticketsTable}.created_at", '>=', $since)
            ->whereNotNull("{$ticketsTable}.sla_policy_id")
            ->groupBy("{$ticketsTable}.department_id", "{$departmentsTable}.name")
            ->select([
                "{$departmentsTable}.name as department",
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN {$ticketsTable}.sla_first_response_breached = 1 OR {$ticketsTable}.sla_resolution_breached = 1 THEN 1 ELSE 0 END) as breached"),
            ])
            ->get()
            ->map(fn ($row) => [
                'department' => $row->department ?? 'Unassigned',
                'total' => (int) $row->total,
                'breached' => (int) $row->breached,
                'breach_rate' => $row->total > 0 ? round(($row->breached / $row->total) * 100, 1) : 0,
            ])
            ->toArray();
    }

    /**
     * Breach rate per priority level.
     */
    public function slaBreachByPriority(int $days): array
    {
        $since = now()->subDays($days);

        return Ticket::where('created_at', '>=', $since)
            ->whereNotNull('sla_policy_id')
            ->groupBy('priority')
            ->select([
                'priority',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN sla_first_response_breached = 1 OR sla_resolution_breached = 1 THEN 1 ELSE 0 END) as breached'),
            ])
            ->get()
            ->map(fn ($row) => [
                'priority' => $row->priority,
                'total' => (int) $row->total,
                'breached' => (int) $row->breached,
                'breach_rate' => $row->total > 0 ? round(($row->breached / $row->total) * 100, 1) : 0,
            ])
            ->toArray();
    }

    /**
     * Tickets approaching SLA breach in next 1h/4h/8h.
     */
    public function slaRiskForecast(): array
    {
        $now = now();

        $windows = [
            '1h' => $now->copy()->addHour(),
            '4h' => $now->copy()->addHours(4),
            '8h' => $now->copy()->addHours(8),
        ];

        $result = [];

        foreach ($windows as $label => $deadline) {
            $frtAtRisk = Ticket::open()
                ->whereNotNull('first_response_due_at')
                ->whereNull('first_response_at')
                ->where('sla_first_response_breached', false)
                ->whereBetween('first_response_due_at', [$now, $deadline])
                ->count();

            $resolutionAtRisk = Ticket::open()
                ->whereNotNull('resolution_due_at')
                ->whereNull('resolved_at')
                ->where('sla_resolution_breached', false)
                ->whereBetween('resolution_due_at', [$now, $deadline])
                ->count();

            $result[$label] = [
                'first_response_at_risk' => $frtAtRisk,
                'resolution_at_risk' => $resolutionAtRisk,
                'total_at_risk' => $frtAtRisk + $resolutionAtRisk,
            ];
        }

        return $result;
    }

    // ──────────────────────────────────────────────────────────────────────
    // First Response Time (FRT)
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Histogram buckets (<1h, 1-4h, 4-8h, 8-24h, >24h) with counts and percentages.
     */
    public function firstResponseTimeDistribution(int $days): array
    {
        $since = now()->subDays($days);
        $hoursDiff = $this->hoursDiffExpression('created_at', 'first_response_at');

        $tickets = Ticket::where('created_at', '>=', $since)
            ->whereNotNull('first_response_at')
            ->selectRaw("{$hoursDiff} as hours_diff")
            ->get();

        return $this->buildHistogram($tickets->pluck('hours_diff'), [
            '<1h' => [0, 1],
            '1-4h' => [1, 4],
            '4-8h' => [4, 8],
            '8-24h' => [8, 24],
            '>24h' => [24, null],
        ]);
    }

    /**
     * Daily average FRT over time.
     */
    public function firstResponseTimeTrend(int $days): array
    {
        $since = now()->subDays($days);
        $dateExpr = $this->dateExpression('created_at');
        $avgHours = $this->avgHoursDiffExpression('created_at', 'first_response_at');

        return Ticket::where('created_at', '>=', $since)
            ->whereNotNull('first_response_at')
            ->selectRaw("{$dateExpr} as date")
            ->selectRaw("{$avgHours} as avg_hours")
            ->selectRaw('COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date,
                'avg_hours' => round((float) $row->avg_hours, 1),
                'count' => (int) $row->count,
            ])
            ->toArray();
    }

    /**
     * Per-agent avg/median/p90 FRT.
     */
    public function firstResponseTimeByAgent(int $days): array
    {
        $since = now()->subDays($days);

        return $this->responseTimeByGrouping($since, 'assigned_to', 'created_at', 'first_response_at');
    }

    /**
     * Per-department FRT.
     */
    public function firstResponseTimeByDepartment(int $days): array
    {
        $since = now()->subDays($days);

        return $this->responseTimeByGrouping($since, 'department_id', 'created_at', 'first_response_at');
    }

    /**
     * Per-priority FRT.
     */
    public function firstResponseTimeByPriority(int $days): array
    {
        $since = now()->subDays($days);

        return $this->responseTimeByGrouping($since, 'priority', 'created_at', 'first_response_at');
    }

    // ──────────────────────────────────────────────────────────────────────
    // Resolution Time
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Histogram (<4h, 4-8h, 8-24h, 1-3d, 3-7d, >7d).
     */
    public function resolutionTimeDistribution(int $days): array
    {
        $since = now()->subDays($days);
        $hoursDiff = $this->hoursDiffExpression('created_at', 'resolved_at');

        $tickets = Ticket::where('created_at', '>=', $since)
            ->whereNotNull('resolved_at')
            ->selectRaw("{$hoursDiff} as hours_diff")
            ->get();

        return $this->buildHistogram($tickets->pluck('hours_diff'), [
            '<4h' => [0, 4],
            '4-8h' => [4, 8],
            '8-24h' => [8, 24],
            '1-3d' => [24, 72],
            '3-7d' => [72, 168],
            '>7d' => [168, null],
        ]);
    }

    /**
     * Daily average resolution time over time.
     */
    public function resolutionTimeTrend(int $days): array
    {
        $since = now()->subDays($days);
        $dateExpr = $this->dateExpression('created_at');
        $avgHours = $this->avgHoursDiffExpression('created_at', 'resolved_at');

        return Ticket::where('created_at', '>=', $since)
            ->whereNotNull('resolved_at')
            ->selectRaw("{$dateExpr} as date")
            ->selectRaw("{$avgHours} as avg_hours")
            ->selectRaw('COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date,
                'avg_hours' => round((float) $row->avg_hours, 1),
                'count' => (int) $row->count,
            ])
            ->toArray();
    }

    /**
     * Per-agent avg/median/p90 resolution time.
     */
    public function resolutionTimeByAgent(int $days): array
    {
        $since = now()->subDays($days);

        return $this->responseTimeByGrouping($since, 'assigned_to', 'created_at', 'resolved_at');
    }

    /**
     * Per-department resolution time.
     */
    public function resolutionTimeByDepartment(int $days): array
    {
        $since = now()->subDays($days);

        return $this->responseTimeByGrouping($since, 'department_id', 'created_at', 'resolved_at');
    }

    /**
     * Per-channel resolution time (email vs chat vs web vs widget).
     */
    public function resolutionTimeByChannel(int $days): array
    {
        $since = now()->subDays($days);

        return $this->responseTimeByGrouping($since, 'channel', 'created_at', 'resolved_at');
    }

    // ──────────────────────────────────────────────────────────────────────
    // Agent Performance Dashboards
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Ranked list with composite score (resolution rate, FRT, CSAT, volume).
     */
    public function agentPerformanceRanking(int $days): array
    {
        $since = now()->subDays($days);
        $end = now();

        $agents = $this->getAgentPerformance($since, $end);
        $ticketsTable = Escalated::table('tickets');
        $ratingsTable = Escalated::table('satisfaction_ratings');

        $ranked = collect($agents)->map(function ($agent) use ($since, $ticketsTable, $ratingsTable) {
            $agentId = $agent->agent_id ?? $agent['agent_id'] ?? null;
            $totalTickets = (int) ($agent->total_tickets ?? $agent['total_tickets'] ?? 0);
            $resolvedTickets = (int) ($agent->resolved_tickets ?? $agent['resolved_tickets'] ?? 0);
            $avgResponseHours = (float) ($agent->avg_response_hours ?? $agent['avg_response_hours'] ?? 0);

            $resolutionRate = $totalTickets > 0 ? ($resolvedTickets / $totalTickets) * 100 : 0;

            // Get CSAT for this agent
            $csatAvg = (float) DB::table($ratingsTable)
                ->join($ticketsTable, "{$ratingsTable}.ticket_id", '=', "{$ticketsTable}.id")
                ->where("{$ticketsTable}.assigned_to", $agentId)
                ->where("{$ratingsTable}.created_at", '>=', $since)
                ->avg("{$ratingsTable}.rating") ?? 0;

            // Composite score: resolution rate (30%) + FRT score (25%) + CSAT (25%) + volume (20%)
            $frtScore = $avgResponseHours > 0 ? max(0, 100 - ($avgResponseHours * 5)) : 50;
            $csatScore = $csatAvg * 20; // Scale 1-5 to 0-100
            $volumeScore = min(100, $totalTickets * 2); // Cap at 100

            $compositeScore = round(
                ($resolutionRate * 0.30) + ($frtScore * 0.25) + ($csatScore * 0.25) + ($volumeScore * 0.20),
                1
            );

            return [
                'agent_id' => $agentId,
                'agent_name' => $agent->agent_name ?? $agent['agent_name'] ?? '',
                'total_tickets' => $totalTickets,
                'resolved_tickets' => $resolvedTickets,
                'resolution_rate' => round($resolutionRate, 1),
                'avg_response_hours' => $avgResponseHours,
                'csat_average' => round($csatAvg, 1),
                'composite_score' => $compositeScore,
            ];
        })
            ->sortByDesc('composite_score')
            ->values()
            ->toArray();

        // Add rank
        foreach ($ranked as $i => &$agent) {
            $agent['rank'] = $i + 1;
        }

        return $ranked;
    }

    /**
     * Tickets per agent over time.
     */
    public function agentWorkloadDistribution(int $days): array
    {
        $since = now()->subDays($days);
        $dateExpr = $this->dateExpression('created_at');

        $userModel = Escalated::newUserModel();
        $usersTable = $userModel->getTable();
        $ticketsTable = Escalated::table('tickets');

        return DB::table($ticketsTable)
            ->join($usersTable, "{$ticketsTable}.assigned_to", '=', "{$usersTable}.id")
            ->where("{$ticketsTable}.created_at", '>=', $since)
            ->whereNotNull("{$ticketsTable}.assigned_to")
            ->selectRaw("{$this->dateExpression("{$ticketsTable}.created_at")} as date")
            ->addSelect("{$usersTable}.name as agent_name")
            ->addSelect("{$ticketsTable}.assigned_to as agent_id")
            ->selectRaw('COUNT(*) as ticket_count')
            ->groupBy('date', "{$usersTable}.name", "{$ticketsTable}.assigned_to")
            ->orderBy('date')
            ->get()
            ->groupBy('agent_name')
            ->map(fn ($rows, $name) => [
                'agent_name' => $name,
                'data' => $rows->map(fn ($row) => [
                    'date' => $row->date,
                    'count' => (int) $row->ticket_count,
                ])->values()->toArray(),
            ])
            ->values()
            ->toArray();
    }

    /**
     * p50, p75, p90, p95, p99 response time percentiles for a specific agent.
     */
    public function agentResponseTimePercentiles(int $agentId, int $days): array
    {
        $since = now()->subDays($days);
        $hoursDiff = $this->hoursDiffExpression('created_at', 'first_response_at');

        $hours = Ticket::where('assigned_to', $agentId)
            ->where('created_at', '>=', $since)
            ->whereNotNull('first_response_at')
            ->selectRaw("{$hoursDiff} as hours_diff")
            ->get()
            ->pluck('hours_diff')
            ->map(fn ($v) => (float) $v)
            ->sort()
            ->values();

        if ($hours->isEmpty()) {
            return ['p50' => 0, 'p75' => 0, 'p90' => 0, 'p95' => 0, 'p99' => 0];
        }

        return [
            'p50' => round($this->percentile($hours, 50), 1),
            'p75' => round($this->percentile($hours, 75), 1),
            'p90' => round($this->percentile($hours, 90), 1),
            'p95' => round($this->percentile($hours, 95), 1),
            'p99' => round($this->percentile($hours, 99), 1),
        ];
    }

    /**
     * Replies per agent per day, tickets resolved per day.
     */
    public function agentProductivity(int $days): array
    {
        $since = now()->subDays($days);
        $ticketsTable = Escalated::table('tickets');
        $repliesTable = Escalated::table('replies');
        $userModel = Escalated::newUserModel();
        $usersTable = $userModel->getTable();

        // Replies per agent
        $replies = DB::table($repliesTable)
            ->join($usersTable, "{$repliesTable}.author_id", '=', "{$usersTable}.id")
            ->where("{$repliesTable}.created_at", '>=', $since)
            ->where("{$repliesTable}.author_type", $userModel::class)
            ->where("{$repliesTable}.is_internal_note", false)
            ->groupBy("{$repliesTable}.author_id", "{$usersTable}.name")
            ->select([
                "{$repliesTable}.author_id as agent_id",
                "{$usersTable}.name as agent_name",
                DB::raw('COUNT(*) as total_replies'),
            ])
            ->get()
            ->keyBy('agent_id');

        // Resolved tickets per agent
        $resolved = Ticket::where('resolved_at', '>=', $since)
            ->whereNotNull('assigned_to')
            ->groupBy('assigned_to')
            ->select([
                'assigned_to as agent_id',
                DB::raw('COUNT(*) as total_resolved'),
            ])
            ->get()
            ->keyBy('agent_id');

        $agentIds = $replies->keys()->merge($resolved->keys())->unique();

        return $agentIds->map(function ($agentId) use ($replies, $resolved, $days) {
            $replyData = $replies->get($agentId);
            $resolvedData = $resolved->get($agentId);
            $totalReplies = $replyData ? (int) $replyData->total_replies : 0;
            $totalResolved = $resolvedData ? (int) $resolvedData->total_resolved : 0;

            return [
                'agent_id' => $agentId,
                'agent_name' => $replyData->agent_name ?? '',
                'total_replies' => $totalReplies,
                'replies_per_day' => round($totalReplies / max($days, 1), 1),
                'total_resolved' => $totalResolved,
                'resolved_per_day' => round($totalResolved / max($days, 1), 1),
            ];
        })
            ->values()
            ->toArray();
    }

    // ──────────────────────────────────────────────────────────────────────
    // Cohort Analysis
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Volume, avg resolution time, breach rate per tag.
     */
    public function ticketsByTag(int $days): array
    {
        $since = now()->subDays($days);
        $ticketsTable = Escalated::table('tickets');
        $tagsTable = Escalated::table('tags');
        $pivotTable = Escalated::table('ticket_tag');
        $hoursDiff = $this->avgHoursDiffRaw("{$ticketsTable}.created_at", "{$ticketsTable}.resolved_at");

        return DB::table($pivotTable)
            ->join($ticketsTable, "{$pivotTable}.ticket_id", '=', "{$ticketsTable}.id")
            ->join($tagsTable, "{$pivotTable}.tag_id", '=', "{$tagsTable}.id")
            ->where("{$ticketsTable}.created_at", '>=', $since)
            ->groupBy("{$tagsTable}.id", "{$tagsTable}.name")
            ->select([
                "{$tagsTable}.name as tag",
                DB::raw('COUNT(*) as volume'),
                DB::raw("ROUND({$hoursDiff}, 1) as avg_resolution_hours"),
                DB::raw("SUM(CASE WHEN {$ticketsTable}.sla_first_response_breached = 1 OR {$ticketsTable}.sla_resolution_breached = 1 THEN 1 ELSE 0 END) as breached"),
            ])
            ->get()
            ->map(fn ($row) => [
                'tag' => $row->tag,
                'volume' => (int) $row->volume,
                'avg_resolution_hours' => (float) ($row->avg_resolution_hours ?? 0),
                'breached' => (int) $row->breached,
                'breach_rate' => $row->volume > 0 ? round(($row->breached / $row->volume) * 100, 1) : 0,
            ])
            ->toArray();
    }

    /**
     * Same metrics per department.
     */
    public function ticketsByDepartment(int $days): array
    {
        $since = now()->subDays($days);
        $ticketsTable = Escalated::table('tickets');
        $departmentsTable = Escalated::table('departments');
        $hoursDiff = $this->avgHoursDiffRaw("{$ticketsTable}.created_at", "{$ticketsTable}.resolved_at");

        return DB::table($ticketsTable)
            ->leftJoin($departmentsTable, "{$ticketsTable}.department_id", '=', "{$departmentsTable}.id")
            ->where("{$ticketsTable}.created_at", '>=', $since)
            ->groupBy("{$ticketsTable}.department_id", "{$departmentsTable}.name")
            ->select([
                "{$departmentsTable}.name as department",
                DB::raw('COUNT(*) as volume'),
                DB::raw("ROUND({$hoursDiff}, 1) as avg_resolution_hours"),
                DB::raw("SUM(CASE WHEN {$ticketsTable}.sla_first_response_breached = 1 OR {$ticketsTable}.sla_resolution_breached = 1 THEN 1 ELSE 0 END) as breached"),
                DB::raw("SUM(CASE WHEN {$ticketsTable}.resolved_at IS NOT NULL THEN 1 ELSE 0 END) as resolved"),
            ])
            ->get()
            ->map(fn ($row) => [
                'department' => $row->department ?? 'Unassigned',
                'volume' => (int) $row->volume,
                'resolved' => (int) $row->resolved,
                'avg_resolution_hours' => (float) ($row->avg_resolution_hours ?? 0),
                'breached' => (int) $row->breached,
                'breach_rate' => $row->volume > 0 ? round(($row->breached / $row->volume) * 100, 1) : 0,
            ])
            ->toArray();
    }

    /**
     * Per channel (web, email, chat, widget).
     */
    public function ticketsByChannel(int $days): array
    {
        $since = now()->subDays($days);
        $hoursDiff = $this->avgHoursDiffExpression('created_at', 'resolved_at');

        return Ticket::where('created_at', '>=', $since)
            ->groupBy('channel')
            ->select([
                'channel',
                DB::raw('COUNT(*) as volume'),
                DB::raw("ROUND({$hoursDiff}, 1) as avg_resolution_hours"),
                DB::raw('SUM(CASE WHEN sla_first_response_breached = 1 OR sla_resolution_breached = 1 THEN 1 ELSE 0 END) as breached'),
                DB::raw('SUM(CASE WHEN resolved_at IS NOT NULL THEN 1 ELSE 0 END) as resolved'),
            ])
            ->get()
            ->map(fn ($row) => [
                'channel' => $row->channel,
                'volume' => (int) $row->volume,
                'resolved' => (int) $row->resolved,
                'avg_resolution_hours' => (float) ($row->avg_resolution_hours ?? 0),
                'breached' => (int) $row->breached,
                'breach_rate' => $row->volume > 0 ? round(($row->breached / $row->volume) * 100, 1) : 0,
            ])
            ->toArray();
    }

    /**
     * Per ticket type.
     */
    public function ticketsByType(int $days): array
    {
        $since = now()->subDays($days);
        $hoursDiff = $this->avgHoursDiffExpression('created_at', 'resolved_at');

        return Ticket::where('created_at', '>=', $since)
            ->groupBy('ticket_type')
            ->select([
                'ticket_type',
                DB::raw('COUNT(*) as volume'),
                DB::raw("ROUND({$hoursDiff}, 1) as avg_resolution_hours"),
                DB::raw('SUM(CASE WHEN sla_first_response_breached = 1 OR sla_resolution_breached = 1 THEN 1 ELSE 0 END) as breached'),
                DB::raw('SUM(CASE WHEN resolved_at IS NOT NULL THEN 1 ELSE 0 END) as resolved'),
            ])
            ->get()
            ->map(fn ($row) => [
                'type' => $row->ticket_type ?? 'unspecified',
                'volume' => (int) $row->volume,
                'resolved' => (int) $row->resolved,
                'avg_resolution_hours' => (float) ($row->avg_resolution_hours ?? 0),
                'breached' => (int) $row->breached,
                'breach_rate' => $row->volume > 0 ? round(($row->breached / $row->volume) * 100, 1) : 0,
            ])
            ->toArray();
    }

    /**
     * Volume trends per priority.
     */
    public function ticketsByPriority(int $days): array
    {
        $since = now()->subDays($days);
        $dateExpr = $this->dateExpression('created_at');

        return Ticket::where('created_at', '>=', $since)
            ->selectRaw("{$dateExpr} as date")
            ->addSelect('priority')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('date', 'priority')
            ->orderBy('date')
            ->get()
            ->groupBy('priority')
            ->map(fn ($rows, $priority) => [
                'priority' => $priority,
                'data' => $rows->map(fn ($row) => [
                    'date' => $row->date,
                    'count' => (int) $row->count,
                ])->values()->toArray(),
            ])
            ->values()
            ->toArray();
    }

    /**
     * Top requesters by volume, repeat ticket rate.
     */
    public function requesterAnalysis(int $days): array
    {
        $since = now()->subDays($days);

        return Ticket::where('created_at', '>=', $since)
            ->whereNotNull('requester_type')
            ->whereNotNull('requester_id')
            ->groupBy('requester_type', 'requester_id')
            ->select([
                'requester_type',
                'requester_id',
                DB::raw('COUNT(*) as ticket_count'),
                DB::raw('MIN(created_at) as first_ticket_at'),
                DB::raw('MAX(created_at) as last_ticket_at'),
            ])
            ->orderByDesc('ticket_count')
            ->limit(50)
            ->get()
            ->map(fn ($row) => [
                'requester_type' => $row->requester_type,
                'requester_id' => $row->requester_id,
                'ticket_count' => (int) $row->ticket_count,
                'is_repeat' => $row->ticket_count > 1,
                'first_ticket_at' => $row->first_ticket_at,
                'last_ticket_at' => $row->last_ticket_at,
            ])
            ->toArray();
    }

    // ──────────────────────────────────────────────────────────────────────
    // Trend & Comparison
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Current period vs previous period for all key metrics (with % change).
     */
    public function periodComparison(int $days): array
    {
        $currentStart = now()->subDays($days);
        $currentEnd = now();
        $previousStart = now()->subDays($days * 2);
        $previousEnd = $currentStart;

        $current = $this->buildPeriodMetrics($currentStart, $currentEnd);
        $previous = $this->buildPeriodMetrics($previousStart, $previousEnd);

        $result = [];
        foreach ($current as $key => $value) {
            $prevValue = $previous[$key] ?? 0;
            $change = $prevValue > 0 ? round((($value - $prevValue) / $prevValue) * 100, 1) : ($value > 0 ? 100.0 : 0);

            $result[$key] = [
                'current' => $value,
                'previous' => $prevValue,
                'change_percent' => $change,
            ];
        }

        return $result;
    }

    /**
     * Simple linear projection based on recent trend.
     */
    public function ticketVolumeForecast(int $days): array
    {
        $since = now()->subDays($days);
        $dateExpr = $this->dateExpression('created_at');

        $dailyCounts = Ticket::where('created_at', '>=', $since)
            ->selectRaw("{$dateExpr} as date, COUNT(*) as count")
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('count', 'date')
            ->map(fn ($v) => (int) $v);

        if ($dailyCounts->count() < 2) {
            return [
                'historical' => $dailyCounts->toArray(),
                'forecast' => [],
                'trend' => 'insufficient_data',
            ];
        }

        // Simple linear regression
        $values = $dailyCounts->values()->toArray();
        $n = count($values);
        $sumX = 0;
        $sumY = 0;
        $sumXY = 0;
        $sumX2 = 0;

        for ($i = 0; $i < $n; $i++) {
            $sumX += $i;
            $sumY += $values[$i];
            $sumXY += $i * $values[$i];
            $sumX2 += $i * $i;
        }

        $denominator = ($n * $sumX2) - ($sumX * $sumX);
        if ($denominator == 0) {
            $slope = 0;
            $intercept = $sumY / $n;
        } else {
            $slope = (($n * $sumXY) - ($sumX * $sumY)) / $denominator;
            $intercept = ($sumY - ($slope * $sumX)) / $n;
        }

        // Forecast next 7 days
        $forecast = [];
        for ($i = 0; $i < 7; $i++) {
            $forecastDate = now()->addDays($i + 1)->format('Y-m-d');
            $predicted = max(0, round($intercept + $slope * ($n + $i)));
            $forecast[$forecastDate] = $predicted;
        }

        $trend = $slope > 0.5 ? 'increasing' : ($slope < -0.5 ? 'decreasing' : 'stable');

        return [
            'historical' => $dailyCounts->toArray(),
            'forecast' => $forecast,
            'trend' => $trend,
            'slope' => round($slope, 2),
        ];
    }

    // ──────────────────────────────────────────────────────────────────────
    // Private Helpers
    // ──────────────────────────────────────────────────────────────────────

    protected function isSqlite(): bool
    {
        return DB::connection()->getDriverName() === 'sqlite';
    }

    protected function dateExpression(string $column): string
    {
        return $this->isSqlite() ? "date({$column})" : "DATE({$column})";
    }

    protected function groupByDateExpression(string $column, ?string $groupBy): string
    {
        if ($groupBy === 'week') {
            return $this->isSqlite()
                ? "strftime('%Y-W%W', {$column})"
                : "DATE_FORMAT({$column}, '%Y-W%v')";
        }

        if ($groupBy === 'month') {
            return $this->isSqlite()
                ? "strftime('%Y-%m', {$column})"
                : "DATE_FORMAT({$column}, '%Y-%m')";
        }

        return $this->dateExpression($column);
    }

    protected function hoursDiffExpression(string $from, string $to): string
    {
        return $this->isSqlite()
            ? "(julianday({$to}) - julianday({$from})) * 24"
            : "TIMESTAMPDIFF(HOUR, {$from}, {$to})";
    }

    protected function avgHoursDiffExpression(string $from, string $to): string
    {
        return $this->isSqlite()
            ? "AVG((julianday({$to}) - julianday({$from})) * 24)"
            : "AVG(TIMESTAMPDIFF(HOUR, {$from}, {$to}))";
    }

    protected function avgHoursDiffRaw(string $from, string $to): string
    {
        return $this->isSqlite()
            ? "AVG((julianday({$to}) - julianday({$from})) * 24)"
            : "AVG(TIMESTAMPDIFF(HOUR, {$from}, {$to}))";
    }

    /**
     * Build a histogram from a collection of numeric values.
     */
    protected function buildHistogram(Collection $values, array $buckets): array
    {
        $total = $values->count();
        $result = [];

        foreach ($buckets as $label => [$min, $max]) {
            $count = $values->filter(function ($v) use ($min, $max) {
                $v = (float) $v;
                if ($max === null) {
                    return $v >= $min;
                }

                return $v >= $min && $v < $max;
            })->count();

            $result[] = [
                'bucket' => $label,
                'count' => $count,
                'percentage' => $total > 0 ? round(($count / $total) * 100, 1) : 0,
            ];
        }

        return $result;
    }

    /**
     * Calculate percentile from a sorted collection.
     */
    protected function percentile(Collection $sorted, float $percentile): float
    {
        $count = $sorted->count();
        if ($count === 0) {
            return 0;
        }

        $index = ($percentile / 100) * ($count - 1);
        $lower = (int) floor($index);
        $upper = (int) ceil($index);
        $fraction = $index - $lower;

        if ($lower === $upper) {
            return (float) $sorted[$lower];
        }

        return (float) $sorted[$lower] + $fraction * ((float) $sorted[$upper] - (float) $sorted[$lower]);
    }

    /**
     * Generic helper to compute avg/median/p90 response time grouped by a column.
     */
    protected function responseTimeByGrouping(Carbon $since, string $groupColumn, string $fromCol, string $toCol): array
    {
        $hoursDiff = $this->hoursDiffExpression($fromCol, $toCol);

        $tickets = Ticket::where('created_at', '>=', $since)
            ->whereNotNull($toCol)
            ->selectRaw("{$groupColumn} as group_key")
            ->selectRaw("{$hoursDiff} as hours_diff")
            ->get()
            ->groupBy('group_key');

        return $tickets->map(function ($rows, $key) {
            $hours = $rows->pluck('hours_diff')->map(fn ($v) => (float) $v)->sort()->values();

            return [
                'group' => $key ?? 'unknown',
                'count' => $hours->count(),
                'avg' => round($hours->avg(), 1),
                'median' => round($this->percentile($hours, 50), 1),
                'p90' => round($this->percentile($hours, 90), 1),
            ];
        })
            ->values()
            ->toArray();
    }

    /**
     * Build key metrics for a period (used by periodComparison).
     */
    protected function buildPeriodMetrics(Carbon $start, Carbon $end): array
    {
        return [
            'total_tickets' => Ticket::whereBetween('created_at', [$start, $end])->count(),
            'resolved_tickets' => Ticket::whereBetween('created_at', [$start, $end])
                ->whereNotNull('resolved_at')->count(),
            'avg_first_response_hours' => $this->getAverageResponseTime($start, $end),
            'avg_resolution_hours' => $this->getAverageResolutionTime($start, $end),
            'sla_compliance_rate' => $this->getSlaComplianceRate($start, $end),
            'csat_average' => $this->getCsatAverage($start, $end),
        ];
    }
}
