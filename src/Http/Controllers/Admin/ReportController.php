<?php

namespace Escalated\Laravel\Http\Controllers\Admin;

use Escalated\Laravel\Contracts\EscalatedUiRenderer;
use Escalated\Laravel\Models\SatisfactionRating;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Services\ReportExportService;
use Escalated\Laravel\Services\ReportingService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function __construct(
        protected ReportingService $reporting,
        protected ReportExportService $exportService,
        protected EscalatedUiRenderer $renderer,
    ) {}

    public function __invoke(Request $request): mixed
    {
        $days = $request->integer('days', 30);
        $since = now()->subDays($days);

        return $this->renderer->render('Escalated/Admin/Reports', [
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

    /**
     * Dashboard with tabs: Overview, Agents, SLA, CSAT.
     */
    public function dashboard(Request $request): mixed
    {
        $days = $request->integer('days', 30);
        $start = now()->subDays($days);
        $end = now();

        return $this->renderer->render('Escalated/Admin/Reports/Dashboard', [
            'period_days' => $days,
            'volume' => $this->reporting->getTicketVolumeByDate($start, $end),
            'by_status' => $this->reporting->getTicketsByStatus(),
            'by_priority' => $this->reporting->getTicketsByPriority(),
            'avg_response_hours' => $this->reporting->getAverageResponseTime($start, $end),
            'avg_resolution_hours' => $this->reporting->getAverageResolutionTime($start, $end),
            'sla_compliance' => $this->reporting->getSlaComplianceRate($start, $end),
            'csat_average' => $this->reporting->getCsatAverage($start, $end),
            'agent_performance' => $this->reporting->getAgentPerformance($start, $end),
        ]);
    }

    /**
     * Agent performance sub-report.
     */
    public function agents(Request $request): mixed
    {
        $days = $request->integer('days', 30);
        $start = now()->subDays($days);
        $end = now();

        return $this->renderer->render('Escalated/Admin/Reports/AgentMetrics', [
            'period_days' => $days,
            'agents' => $this->reporting->getAgentPerformance($start, $end),
        ]);
    }

    /**
     * SLA compliance sub-report.
     */
    public function sla(Request $request): mixed
    {
        $days = $request->integer('days', 30);
        $start = now()->subDays($days);
        $end = now();

        return $this->renderer->render('Escalated/Admin/Reports/SlaReport', [
            'period_days' => $days,
            'compliance_rate' => $this->reporting->getSlaComplianceRate($start, $end),
            'compliance_by_policy' => $this->reporting->getSlaComplianceByPolicy($start, $end),
            'breaches' => $this->reporting->getSlaBreachDetails($start, $end),
        ]);
    }

    /**
     * CSAT analytics sub-report.
     */
    public function csat(Request $request): mixed
    {
        $days = $request->integer('days', 30);
        $start = now()->subDays($days);
        $end = now();

        $totalTickets = Ticket::whereBetween('created_at', [$start, $end])->count();
        $totalRatings = SatisfactionRating::whereBetween('created_at', [$start, $end])->count();

        return $this->renderer->render('Escalated/Admin/Reports/CsatReport', [
            'period_days' => $days,
            'csat_average' => $this->reporting->getCsatAverage($start, $end),
            'response_rate' => $totalTickets > 0 ? round(($totalRatings / $totalTickets) * 100, 1) : 0,
            'total_ratings' => $totalRatings,
            'by_agent' => $this->reporting->getCsatByAgent($start, $end),
            'over_time' => $this->reporting->getCsatOverTime($start, $end),
        ]);
    }

    /**
     * SLA breach trends (daily/weekly/monthly, by dept, by priority).
     */
    public function slaTrends(Request $request): mixed
    {
        $days = $this->periodDays($request);
        $groupBy = $request->input('group_by', 'day');

        return $this->renderer->render('Escalated/Admin/Reports/SlaTrends', [
            'period_days' => $days,
            'trends' => $this->reporting->slaBreachTrends($days, $groupBy),
            'by_department' => $this->reporting->slaBreachByDepartment($days),
            'by_priority' => $this->reporting->slaBreachByPriority($days),
            'risk_forecast' => $this->reporting->slaRiskForecast(),
        ]);
    }

    /**
     * First response time analytics.
     */
    public function firstResponseTime(Request $request): mixed
    {
        $days = $this->periodDays($request);

        return $this->renderer->render('Escalated/Admin/Reports/FirstResponseTime', [
            'period_days' => $days,
            'distribution' => $this->reporting->firstResponseTimeDistribution($days),
            'trend' => $this->reporting->firstResponseTimeTrend($days),
            'by_agent' => $this->reporting->firstResponseTimeByAgent($days),
            'by_department' => $this->reporting->firstResponseTimeByDepartment($days),
            'by_priority' => $this->reporting->firstResponseTimeByPriority($days),
        ]);
    }

    /**
     * Resolution time analytics.
     */
    public function resolutionTime(Request $request): mixed
    {
        $days = $this->periodDays($request);

        return $this->renderer->render('Escalated/Admin/Reports/ResolutionTime', [
            'period_days' => $days,
            'distribution' => $this->reporting->resolutionTimeDistribution($days),
            'trend' => $this->reporting->resolutionTimeTrend($days),
            'by_agent' => $this->reporting->resolutionTimeByAgent($days),
            'by_department' => $this->reporting->resolutionTimeByDepartment($days),
            'by_channel' => $this->reporting->resolutionTimeByChannel($days),
        ]);
    }

    /**
     * Agent performance ranking.
     */
    public function agentRanking(Request $request): mixed
    {
        $days = $this->periodDays($request);

        return $this->renderer->render('Escalated/Admin/Reports/AgentRanking', [
            'period_days' => $days,
            'ranking' => $this->reporting->agentPerformanceRanking($days),
            'workload' => $this->reporting->agentWorkloadDistribution($days),
            'productivity' => $this->reporting->agentProductivity($days),
        ]);
    }

    /**
     * Individual agent deep-dive.
     */
    public function agentDetail(Request $request, int $id): mixed
    {
        $days = $this->periodDays($request);
        $start = now()->subDays($days);
        $end = now();

        return $this->renderer->render('Escalated/Admin/Reports/AgentDetail', [
            'period_days' => $days,
            'agent_id' => $id,
            'metrics' => $this->reporting->getAgentMetrics($id, $start, $end),
            'percentiles' => $this->reporting->agentResponseTimePercentiles($id, $days),
        ]);
    }

    /**
     * Cohort analysis (by tag, dept, channel, type).
     */
    public function cohortAnalysis(Request $request): mixed
    {
        $days = $this->periodDays($request);

        return $this->renderer->render('Escalated/Admin/Reports/CohortAnalysis', [
            'period_days' => $days,
            'by_tag' => $this->reporting->ticketsByTag($days),
            'by_department' => $this->reporting->ticketsByDepartment($days),
            'by_channel' => $this->reporting->ticketsByChannel($days),
            'by_type' => $this->reporting->ticketsByType($days),
            'by_priority' => $this->reporting->ticketsByPriority($days),
            'requester_analysis' => $this->reporting->requesterAnalysis($days),
        ]);
    }

    /**
     * Period comparison (current vs previous).
     */
    public function periodComparison(Request $request): mixed
    {
        $days = $this->periodDays($request);

        return $this->renderer->render('Escalated/Admin/Reports/PeriodComparison', [
            'period_days' => $days,
            'comparison' => $this->reporting->periodComparison($days),
            'forecast' => $this->reporting->ticketVolumeForecast($days),
        ]);
    }

    /**
     * Export report as CSV or JSON.
     */
    public function export(Request $request, string $type): mixed
    {
        $format = $request->input('format', 'csv');
        $filters = [
            'period' => $this->periodDays($request),
        ];

        if ($format === 'json') {
            return $this->exportService->exportToJson($type, $filters);
        }

        return $this->exportService->exportToCsv($type, $filters);
    }

    protected function periodDays(Request $request): int
    {
        return $request->integer('period', 30);
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
