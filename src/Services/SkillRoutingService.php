<?php

namespace Escalated\Laravel\Services;

use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Escalated;
use Escalated\Laravel\Models\Ticket;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SkillRoutingService
{
    /**
     * Find agents whose skill assignments satisfy every skill required for this
     * ticket's explicit routing rules, ordered by total proficiency (desc) then
     * current open-ticket load (asc).
     *
     * Required skills are those with a routing tag matching any ticket tag, or
     * a routing department matching the ticket's department.
     */
    public function findMatchingAgents(Ticket $ticket): Collection
    {
        $ticketTagPivot = Escalated::table('ticket_tag');

        $tagIds = DB::table($ticketTagPivot)
            ->where('ticket_id', $ticket->getKey())
            ->pluck('tag_id')
            ->all();

        $routingTagsTable = Escalated::table('skill_routing_tags');
        $routingDeptsTable = Escalated::table('skill_routing_departments');

        $skillIdsFromTags = DB::table($routingTagsTable)
            ->whereIn('tag_id', $tagIds)
            ->pluck('skill_id')
            ->all();

        $skillIdsFromDepartments = [];

        if ($ticket->department_id) {
            $skillIdsFromDepartments = DB::table($routingDeptsTable)
                ->where('department_id', $ticket->department_id)
                ->pluck('skill_id')
                ->all();
        }

        $requiredSkillIds = array_values(array_unique(array_merge(
            $skillIdsFromTags,
            $skillIdsFromDepartments,
        )));

        if ($requiredSkillIds === []) {
            return collect();
        }

        $agentSkillTable = Escalated::table('agent_skill');
        $requiredCount = count($requiredSkillIds);

        $rows = DB::table($agentSkillTable)
            ->selectRaw('user_id, SUM(proficiency) as proficiency_sum')
            ->whereIn('skill_id', $requiredSkillIds)
            ->groupBy('user_id')
            ->havingRaw('COUNT(DISTINCT skill_id) = ?', [$requiredCount])
            ->get();

        if ($rows->isEmpty()) {
            return collect();
        }

        $userModel = Escalated::userModel();
        /** @var Model $probe */
        $probe = new $userModel;

        /** @var \Illuminate\Database\Eloquent\Collection<int, Model> $agents */
        $agents = $userModel::query()
            ->whereIn($probe->getKeyName(), $rows->pluck('user_id')->all())
            ->withCount([
                'escalatedAssignedTickets as open_tickets_count' => function ($q): void {
                    $q->whereNotIn('status', [
                        TicketStatus::Resolved,
                        TicketStatus::Closed,
                    ]);
                },
            ])
            ->get();

        $weights = $rows->mapWithKeys(
            fn ($row) => [(int) $row->user_id => (int) $row->proficiency_sum],
        );

        return $agents
            ->sort(function ($a, $b) use ($weights): int {
                $sumA = (int) ($weights[(int) $a->getKey()] ?? 0);
                $sumB = (int) ($weights[(int) $b->getKey()] ?? 0);

                if ($sumA !== $sumB) {
                    return $sumB <=> $sumA;
                }

                $loadA = (int) ($a->open_tickets_count ?? 0);
                $loadB = (int) ($b->open_tickets_count ?? 0);

                return $loadA <=> $loadB;
            })
            ->values();
    }
}
