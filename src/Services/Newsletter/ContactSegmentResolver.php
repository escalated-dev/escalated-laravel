<?php

namespace Escalated\Laravel\Services\Newsletter;

use Escalated\Laravel\Models\Contact;
use Escalated\Laravel\Models\Newsletter\NewsletterList;
use Illuminate\Database\Eloquent\Builder;

class ContactSegmentResolver
{
    /**
     * Resolve a list to its full set of contact IDs (no opt-out filtering).
     *
     * @return array<int>
     */
    public function resolve(NewsletterList $list): array
    {
        if ($list->kind === 'static') {
            return $list->members()->pluck('contact_id')->all();
        }

        return $this->applyFilter($list->filter_json ?? ['rules' => []])->pluck('id')->all();
    }

    /**
     * Resolve to sendable contact IDs (opt-out filtered).
     * Caller may further exclude hard-bounced emails via BounceSuppressionStore.
     *
     * @return array<int>
     */
    public function resolveSendable(NewsletterList $list): array
    {
        $query = Contact::query()->whereNull('marketing_opt_out_at');

        if ($list->kind === 'static') {
            $query->whereIn('id', $list->members()->pluck('contact_id'));
        } else {
            $query = $this->applyFilter($list->filter_json ?? ['rules' => []], $query);
        }

        return $query->pluck('id')->all();
    }

    /**
     * Count contacts matching a dynamic filter, ignoring opt-outs.
     * Used by the admin UI's live counter.
     */
    public function countMatches(array $filter): int
    {
        return $this->applyFilter($filter)->count();
    }

    protected function applyFilter(array $filter, ?Builder $base = null): Builder
    {
        $query = $base ?? Contact::query();
        foreach ($filter['rules'] ?? [] as $rule) {
            $field = $rule['field'] ?? null;
            $op = $rule['op'] ?? '=';
            $value = $rule['value'] ?? null;
            if (! $field) {
                continue;
            }

            if (str_starts_with($field, 'metadata.')) {
                $key = substr($field, strlen('metadata.'));
                $query->whereJsonContains("metadata->{$key}", $value);
                continue;
            }
            $query->where($field, $op, $value);
        }

        return $query;
    }
}
