<?php

namespace Escalated\Laravel\Support;

use Escalated\Laravel\Escalated;
use Escalated\Laravel\Models\Department;
use Escalated\Laravel\Models\Ticket;
use Illuminate\Support\Carbon;

/**
 * The figures the admin report screens read, in the shape they read them.
 *
 * ReportingService answers in the shape the export and the API use: rows keyed
 * by what they measure (`bucket`, `period`, `group`, `total_breaches`). The
 * screens in @escalated-dev/escalated read something flatter. Every chart takes
 * a list of `{label, value}`, the agent tables and cohort tables read their own
 * column names, and the SLA screen lists the tickets at risk rather than counts
 * of them. Sent the service rows as they are, the charts drew NaN-high bars and
 * the SLA screen threw on `at_risk_tickets.filter`.
 *
 * The mapping lives here rather than in the service so the export keeps its
 * columns, and it matches the other hosts' screens (ReportScreenMetrics in the
 * Adonis and Django ports).
 */
final class ReportScreens
{
    /**
     * Every chart on the report screens reads `{label, value}`.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return list<array{label: mixed, value: float|int}>
     */
    public static function series(array $rows, string $labelKey, string $valueKey): array
    {
        return array_values(array_map(fn (array $row) => [
            'label' => self::label($row[$labelKey] ?? null),
            'value' => $row[$valueKey] ?? 0,
        ], $rows));
    }

    /**
     * The histogram buckets from ReportingService::buildHistogram().
     *
     * @param  array<int, array{bucket: string, count: int}>  $buckets
     * @return list<array{label: string, value: int}>
     */
    public static function distribution(array $buckets): array
    {
        return self::series($buckets, 'bucket', 'count');
    }

    /**
     * Average hours per department, from rows grouped on `department_id`.
     *
     * @param  array<int, array{group: mixed, avg: float}>  $rows
     * @return list<array{label: string, value: float}>
     */
    public static function averageByDepartment(array $rows): array
    {
        $ids = array_filter(array_column($rows, 'group'), fn ($id) => $id !== null && $id !== '' && $id !== 'unknown');
        $names = $ids === [] ? [] : Department::query()->whereKey($ids)->pluck('name', 'id')->all();

        return array_values(array_map(fn (array $row) => [
            'label' => $names[$row['group']] ?? 'Unassigned',
            'value' => $row['avg'] ?? 0,
        ], $rows));
    }

    /**
     * Average hours per value of a plain column such as priority or channel.
     *
     * @param  array<int, array{group: mixed, avg: float}>  $rows
     * @return list<array{label: string, value: float}>
     */
    public static function averageBy(array $rows): array
    {
        return array_values(array_map(fn (array $row) => [
            'label' => self::label($row['group'] ?? null),
            'value' => $row['avg'] ?? 0,
        ], $rows));
    }

    /**
     * The agent table on the response- and resolution-time screens, from rows
     * grouped on `assigned_to`. Unassigned tickets are not an agent.
     *
     * @param  array<int, array{group: mixed, count: int, avg: float, median: float, p90: float}>  $rows
     * @return list<array{agent_id: mixed, agent_name: string, count: int, avg: float, median: float, p90: float}>
     */
    public static function agentTimes(array $rows): array
    {
        $rows = array_values(array_filter($rows, fn (array $row) => ! in_array($row['group'] ?? null, [null, '', 'unknown'], true)));
        $names = self::agentNames(array_column($rows, 'group'));

        return array_map(fn (array $row) => [
            'agent_id' => $row['group'],
            'agent_name' => $names[$row['group']] ?? (string) $row['group'],
            'count' => $row['count'],
            'avg' => $row['avg'],
            'median' => $row['median'],
            'p90' => $row['p90'],
        ], $rows);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows  from agentPerformanceRanking()
     * @return list<array<string, mixed>>
     */
    public static function agentRanking(array $rows): array
    {
        return array_values(array_map(fn (array $row) => [
            'agent_id' => $row['agent_id'],
            'agent_name' => $row['agent_name'],
            'volume' => $row['total_tickets'] ?? 0,
            'resolution_rate' => $row['resolution_rate'] ?? 0,
            'avg_frt' => $row['avg_response_hours'] ?? 0,
            'avg_resolution' => $row['avg_resolution_hours'] ?? 0,
            'csat' => $row['csat_average'] ?? 0,
            'composite_score' => $row['composite_score'] ?? 0,
        ], $rows));
    }

    /**
     * A cohort table and its chart read `name`, `volume`, `avg_resolution`,
     * `breach_rate` and `csat`.
     *
     * @param  array<int, array<string, mixed>>  $rows  from ticketsByTag() and its siblings
     * @return list<array{name: string, volume: int, avg_resolution: float, breach_rate: float, csat: float}>
     */
    public static function cohorts(array $rows, string $nameKey): array
    {
        return array_values(array_map(fn (array $row) => [
            'name' => self::label($row[$nameKey] ?? null),
            'volume' => $row['volume'] ?? 0,
            'avg_resolution' => $row['avg_resolution_hours'] ?? 0,
            'breach_rate' => $row['breach_rate'] ?? 0,
            'csat' => $row['csat'] ?? 0,
        ], $rows));
    }

    /**
     * The SLA screen charts breaches over time as one line and as a stacked
     * bar split by first response and resolution.
     *
     * @param  array<int, array{period: string, first_response_breaches: int, resolution_breaches: int, total_breaches: int}>  $rows
     * @return array{total: list<array{label: string, value: int}>, by_type: list<array{label: string, values: array{int, int}}>}
     */
    public static function breachTrends(array $rows): array
    {
        return [
            'total' => self::series($rows, 'period', 'total_breaches'),
            'by_type' => array_values(array_map(fn (array $row) => [
                'label' => $row['period'],
                'values' => [$row['first_response_breaches'], $row['resolution_breaches']],
            ], $rows)),
        ];
    }

    /**
     * Open tickets whose next SLA deadline falls within the window, soonest
     * first. The screen sorts them into <=1h, <=4h and <=8h itself.
     *
     * @return list<array{id: int, reference: string, subject: string, hours_remaining: float}>
     */
    public static function atRiskTickets(int $withinHours = 8, int $limit = 50): array
    {
        $now = Carbon::now();
        $deadline = $now->copy()->addHours($withinHours);

        return Ticket::open()
            ->where(function ($query) use ($now, $deadline) {
                $query->where(function ($frt) use ($now, $deadline) {
                    $frt->whereNull('first_response_at')
                        ->where('sla_first_response_breached', false)
                        ->whereBetween('first_response_due_at', [$now, $deadline]);
                })->orWhere(function ($resolution) use ($now, $deadline) {
                    $resolution->whereNull('resolved_at')
                        ->where('sla_resolution_breached', false)
                        ->whereBetween('resolution_due_at', [$now, $deadline]);
                });
            })
            ->get(['id', 'reference', 'subject', 'first_response_at', 'first_response_due_at', 'resolution_due_at', 'resolved_at'])
            ->map(function (Ticket $ticket) use ($now, $deadline) {
                $due = collect([
                    $ticket->first_response_at === null ? $ticket->first_response_due_at : null,
                    $ticket->resolved_at === null ? $ticket->resolution_due_at : null,
                ])->filter(fn ($at) => $at !== null && $at->between($now, $deadline))->min();

                return [
                    'id' => $ticket->id,
                    'reference' => $ticket->reference,
                    'subject' => $ticket->subject,
                    'hours_remaining' => round($now->diffInMinutes($due) / 60, 1),
                ];
            })
            ->sortBy('hours_remaining')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * Chart labels and cohort names are text; the rows hold enum cases for
     * channel and priority, and nothing at all for tickets without one.
     */
    private static function label(mixed $value): string
    {
        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        return $value === null || $value === '' ? 'unknown' : (string) $value;
    }

    /**
     * @param  array<int, mixed>  $ids
     * @return array<int|string, string>
     */
    private static function agentNames(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $column = Escalated::userSearchableDisplayColumn();

        return Escalated::newUserModel()->newQuery()
            ->whereKey($ids)
            ->get()
            ->mapWithKeys(fn ($user) => [$user->getKey() => (string) ($user->{$column} ?? $user->email ?? $user->getKey())])
            ->all();
    }
}
