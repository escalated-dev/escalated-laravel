<?php

namespace Escalated\Laravel\Services;

use Escalated\Laravel\Escalated;
use Escalated\Laravel\Models\Skill;
use Escalated\Laravel\Models\Ticket;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SkillRoutingService
{
    /**
     * Find agents with skills matching ticket tags and/or department,
     * sorted by skill coverage, total proficiency, then current load.
     *
     * Explicit routing rules win: a skill may target ticket tags and/or
     * departments. If a skill has no routing arrays configured, we fall
     * back to name-based tag matching to preserve earlier placeholder
     * behavior.
     */
    public function findMatchingAgents(Ticket $ticket): Collection
    {
        $tagIds = $ticket->tags()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $tagNames = $ticket->tags()->pluck('name')->map(fn ($name) => mb_strtolower((string) $name))->all();
        $departmentId = $ticket->department_id ? (int) $ticket->department_id : null;

        if (empty($tagIds) && $departmentId === null && empty($tagNames)) {
            return collect();
        }

        $matchingSkills = Skill::query()
            ->get()
            ->filter(function (Skill $skill) use ($tagIds, $tagNames, $departmentId) {
                $routingTagIds = array_map('intval', $skill->routing_tag_ids ?? []);
                $routingDepartmentIds = array_map('intval', $skill->routing_department_ids ?? []);
                $hasExplicitRules = $routingTagIds !== [] || $routingDepartmentIds !== [];

                if ($hasExplicitRules) {
                    return ($routingTagIds !== [] && array_intersect($routingTagIds, $tagIds) !== [])
                        || ($departmentId !== null && in_array($departmentId, $routingDepartmentIds, true));
                }

                return in_array(mb_strtolower($skill->name), $tagNames, true);
            })
            ->values();

        if ($matchingSkills->isEmpty()) {
            return collect();
        }

        $agentSkillTable = Escalated::table('agent_skill');
        $skillIds = $matchingSkills->pluck('id')->all();

        $agentSkillRows = DB::table($agentSkillTable)
            ->whereIn('skill_id', $skillIds)
            ->get(['user_id', 'skill_id', 'proficiency']);

        if ($agentSkillRows->isEmpty()) {
            return collect();
        }

        $userModel = Escalated::userModel();
        $userKey = (new $userModel)->getKeyName();
        $agentIds = $agentSkillRows->pluck('user_id')->unique()->values();

        $openTicketCounts = Ticket::query()
            ->open()
            ->whereIn('assigned_to', $agentIds->all())
            ->selectRaw('assigned_to, COUNT(*) as aggregate')
            ->groupBy('assigned_to')
            ->pluck('aggregate', 'assigned_to');

        $rowsByUser = $agentSkillRows->groupBy('user_id');
        $agents = $userModel::query()
            ->whereIn($userKey, $agentIds->all())
            ->get()
            ->map(function ($agent) use ($rowsByUser, $openTicketCounts, $skillIds) {
                $rows = $rowsByUser->get($agent->getKey(), collect());
                $matchedSkillIds = $rows->pluck('skill_id')->map(fn ($id) => (int) $id)->intersect($skillIds)->unique();
                $agent->matched_skill_count = $matchedSkillIds->count();
                $agent->total_skill_proficiency = (int) $rows->sum('proficiency');
                $agent->open_tickets_count = (int) ($openTicketCounts[$agent->getKey()] ?? 0);

                return $agent;
            })
            ->filter(fn ($agent) => $agent->matched_skill_count > 0)
            ->values();

        return $agents->sort(function ($left, $right) {
            return [
                $right->matched_skill_count,
                $right->total_skill_proficiency,
                $left->open_tickets_count,
                mb_strtolower((string) $left->name),
            ] <=> [
                $left->matched_skill_count,
                $left->total_skill_proficiency,
                $right->open_tickets_count,
                mb_strtolower((string) $right->name),
            ];
        })->values();
    }
}
