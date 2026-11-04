<?php

namespace Escalated\Laravel\Support;

use Escalated\Laravel\Contracts\Ticketable;
use Escalated\Laravel\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Builder;

/**
 * The ticket list's filters, applied to a ticket query.
 *
 * The list and the agent inbox counts both go through here, so a count and
 * the list it labels cannot disagree about what a filter means.
 */
final class TicketListFilters
{
    /** How close a due SLA target has to be for the ticket to be "at risk". */
    public const SLA_AT_RISK_MINUTES = 60;

    /** Validation rules for the filters added for the agent panel. */
    public static function rules(): array
    {
        return [
            'open' => ['nullable', 'boolean'],
            'sla_at_risk' => ['nullable', 'boolean'],
            'resolved_today' => ['nullable', 'boolean'],
            'updated_within_hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
        ];
    }

    public static function apply(Builder $query, array $filters = [], ?Ticketable $for = null): Builder
    {
        if ($for) {
            $query->where('requester_type', $for->getMorphClass())
                ->where('requester_id', $for->getKey());
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (self::flag($filters, 'open')) {
            $query->open();
        }

        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (! empty($filters['ticket_type'])) {
            $query->where('ticket_type', $filters['ticket_type']);
        }

        if (! empty($filters['assigned_to'])) {
            $query->where('assigned_to', $filters['assigned_to']);
        }

        if (isset($filters['unassigned']) && $filters['unassigned']) {
            $query->whereNull('assigned_to');
        }

        if (! empty($filters['department_id'])) {
            $query->where('department_id', $filters['department_id']);
        }

        if (isset($filters['account_type']) || isset($filters['account_id'])) {
            TicketAccounts::constrain($query, ['type' => $filters['account_type'] ?? null, 'id' => $filters['account_id'] ?? null]);
        }

        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (isset($filters['sla_breached']) && $filters['sla_breached']) {
            $query->breachedSla();
        }

        if (self::flag($filters, 'sla_at_risk')) {
            self::slaAtRisk($query);
        }

        if (self::flag($filters, 'resolved_today')) {
            $query->where('resolved_at', '>=', now()->startOfDay());
        }

        if (! empty($filters['updated_within_hours']) && is_numeric($filters['updated_within_hours'])) {
            $query->where('updated_at', '>=', now()->subHours((int) $filters['updated_within_hours']));
        }

        if (! empty($filters['tag_ids'])) {
            $query->whereHas('tags', fn ($q) => $q->whereIn('id', $filters['tag_ids']));
        }

        if (! empty($filters['tag'])) {
            $query->whereHas('tags', fn ($q) => $q->where('name', 'like', "%{$filters['tag']}%"));
        }

        if (! empty($filters['created_after'])) {
            $query->where('created_at', '>=', $filters['created_after']);
        }

        if (! empty($filters['created_before'])) {
            $query->where('created_at', '<=', $filters['created_before']);
        }

        if (isset($filters['has_attachments']) && $filters['has_attachments']) {
            $query->whereHas('attachments');
        }

        if (! empty($filters['requester'])) {
            $term = $filters['requester'];
            $query->where(function ($q) use ($term) {
                $q->where('guest_name', 'like', "%{$term}%")
                    ->orWhere('guest_email', 'like', "%{$term}%")
                    ->orWhere(fn ($rq) => $rq->whereRequesterMatches($term));
            });
        }

        if (isset($filters['following']) && $filters['following'] && $for) {
            $query->whereHas('followers', fn ($q) => $q->where('user_id', $for->getKey()));
        }

        // Snooze filtering: show only snoozed, or exclude snoozed by default
        if (isset($filters['snoozed']) && $filters['snoozed']) {
            $query->snoozed();
        } elseif (! isset($filters['include_snoozed']) || ! $filters['include_snoozed']) {
            $query->notSnoozed();
        }

        return $query;
    }

    /**
     * Unresolved tickets whose SLA is already breached, or whose first
     * response (still unsent) or resolution falls due within the hour.
     */
    private static function slaAtRisk(Builder $query): void
    {
        $soon = now()->addMinutes(self::SLA_AT_RISK_MINUTES);

        $query->whereNotIn('status', [TicketStatus::Resolved->value, TicketStatus::Closed->value])
            ->where(function ($q) use ($soon) {
                $q->where('sla_first_response_breached', true)
                    ->orWhere('sla_resolution_breached', true)
                    ->orWhere(fn ($r) => $r->whereNull('first_response_at')->where('first_response_due_at', '<=', $soon))
                    ->orWhere('resolution_due_at', '<=', $soon);
            });
    }

    private static function flag(array $filters, string $key): bool
    {
        return isset($filters[$key]) && filter_var($filters[$key], FILTER_VALIDATE_BOOLEAN);
    }
}
