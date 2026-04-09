<?php

namespace Escalated\Laravel\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ReportExportService
{
    protected const SUPPORTED_TYPES = [
        'tickets',
        'agent_performance',
        'sla_compliance',
        'csat',
        'resolution_times',
        'first_response_times',
    ];

    public function __construct(
        protected ReportingService $reporting,
    ) {}

    /**
     * Export report data as a CSV response.
     */
    public function exportToCsv(string $reportType, array $filters): Response
    {
        $this->validateReportType($reportType);

        $data = $this->getReportData($reportType, $filters);

        $filename = "{$reportType}_report_".now()->format('Y-m-d_His').'.csv';

        $handle = fopen('php://temp', 'r+');

        if (empty($data)) {
            fputcsv($handle, ['No data available']);
        } else {
            // Write header row from first item keys
            $first = reset($data);
            if (is_array($first)) {
                fputcsv($handle, array_keys($this->flattenRow($first)));
            }

            // Write data rows
            foreach ($data as $row) {
                if (is_array($row)) {
                    fputcsv($handle, array_values($this->flattenRow($row)));
                }
            }
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return new Response($content, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * Export report data as JSON for BI tools.
     */
    public function exportToJson(string $reportType, array $filters): JsonResponse
    {
        $this->validateReportType($reportType);

        $data = $this->getReportData($reportType, $filters);

        return response()->json([
            'report_type' => $reportType,
            'generated_at' => now()->toIso8601String(),
            'filters' => $filters,
            'record_count' => count($data),
            'data' => $data,
        ]);
    }

    /**
     * Get supported report types.
     */
    public static function supportedTypes(): array
    {
        return self::SUPPORTED_TYPES;
    }

    /**
     * Resolve report data based on type and filters.
     */
    protected function getReportData(string $reportType, array $filters): array
    {
        $days = (int) ($filters['period'] ?? 30);
        $start = now()->subDays($days);
        $end = now();

        return match ($reportType) {
            'tickets' => $this->getTicketsReport($days, $start, $end),
            'agent_performance' => $this->reporting->agentPerformanceRanking($days),
            'sla_compliance' => $this->getSlaComplianceReport($days, $start, $end),
            'csat' => $this->getCsatReport($start, $end),
            'resolution_times' => $this->reporting->resolutionTimeDistribution($days),
            'first_response_times' => $this->reporting->firstResponseTimeDistribution($days),
            default => [],
        };
    }

    protected function getTicketsReport(int $days, $start, $end): array
    {
        $volume = $this->reporting->getTicketVolumeByDate($start, $end);
        $byStatus = $this->reporting->getTicketsByStatus();
        $byPriority = $this->reporting->getTicketsByPriority();

        return array_merge(
            array_map(fn ($item) => array_merge($item, ['metric' => 'daily_volume']), $volume),
            array_map(fn ($item) => array_merge($item, ['metric' => 'by_status']), $byStatus),
            array_map(fn ($item) => array_merge($item, ['metric' => 'by_priority']), $byPriority),
        );
    }

    protected function getSlaComplianceReport(int $days, $start, $end): array
    {
        $byPolicy = $this->reporting->getSlaComplianceByPolicy($start, $end);
        $byDepartment = $this->reporting->slaBreachByDepartment($days);
        $byPriority = $this->reporting->slaBreachByPriority($days);

        return array_merge(
            array_map(fn ($item) => array_merge($item, ['metric' => 'by_policy']), $byPolicy),
            array_map(fn ($item) => array_merge($item, ['metric' => 'by_department']), $byDepartment),
            array_map(fn ($item) => array_merge($item, ['metric' => 'by_priority']), $byPriority),
        );
    }

    protected function getCsatReport($start, $end): array
    {
        $byAgent = $this->reporting->getCsatByAgent($start, $end);
        $overTime = $this->reporting->getCsatOverTime($start, $end);

        return array_merge(
            array_map(fn ($item) => array_merge((array) $item, ['metric' => 'by_agent']), $byAgent),
            array_map(fn ($item) => array_merge($item, ['metric' => 'over_time']), $overTime),
        );
    }

    protected function validateReportType(string $type): void
    {
        if (! in_array($type, self::SUPPORTED_TYPES, true)) {
            throw new \InvalidArgumentException(
                "Unsupported report type: {$type}. Supported types: ".implode(', ', self::SUPPORTED_TYPES)
            );
        }
    }

    /**
     * Flatten nested arrays and enums for CSV output.
     */
    protected function flattenRow(array $row): array
    {
        $flat = [];
        foreach ($row as $key => $value) {
            if (is_array($value)) {
                $flat[$key] = json_encode($value);
            } elseif ($value instanceof \BackedEnum) {
                $flat[$key] = $value->value;
            } elseif ($value instanceof \UnitEnum) {
                $flat[$key] = $value->name;
            } else {
                $flat[$key] = $value;
            }
        }

        return $flat;
    }
}
