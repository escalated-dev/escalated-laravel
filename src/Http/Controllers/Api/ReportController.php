<?php

namespace Escalated\Laravel\Http\Controllers\Api;

use Escalated\Laravel\Services\ReportExportService;
use Escalated\Laravel\Services\ReportingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ReportController extends Controller
{
    public function __construct(
        protected ReportingService $reporting,
        protected ReportExportService $exportService,
    ) {}

    /**
     * Dashboard summary JSON for API consumers.
     */
    public function summary(Request $request): JsonResponse
    {
        $days = $request->integer('period', 30);
        $start = now()->subDays($days);
        $end = now();

        return response()->json([
            'period_days' => $days,
            'avg_first_response_hours' => $this->reporting->getAverageResponseTime($start, $end),
            'avg_resolution_hours' => $this->reporting->getAverageResolutionTime($start, $end),
            'sla_compliance_rate' => $this->reporting->getSlaComplianceRate($start, $end),
            'csat_average' => $this->reporting->getCsatAverage($start, $end),
            'volume' => $this->reporting->getTicketVolumeByDate($start, $end),
            'by_status' => $this->reporting->getTicketsByStatus(),
            'by_priority' => $this->reporting->getTicketsByPriority(),
            'comparison' => $this->reporting->periodComparison($days),
        ]);
    }

    /**
     * Export report data as CSV or JSON.
     */
    public function export(Request $request, string $type): mixed
    {
        $format = $request->input('format', 'json');
        $filters = [
            'period' => $request->integer('period', 30),
        ];

        if ($format === 'csv') {
            return $this->exportService->exportToCsv($type, $filters);
        }

        return $this->exportService->exportToJson($type, $filters);
    }
}
