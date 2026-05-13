<?php

namespace Escalated\Laravel\Http\Controllers\Admin;

use Escalated\Laravel\Contracts\EscalatedUiRenderer;
use Escalated\Laravel\Escalated;
use Escalated\Laravel\Http\Requests\Admin\StoreSkillRequest;
use Escalated\Laravel\Http\Requests\Admin\UpdateSkillRequest;
use Escalated\Laravel\Models\Department;
use Escalated\Laravel\Models\Skill;
use Escalated\Laravel\Models\Tag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SkillController extends Controller
{
    public function __construct(
        protected EscalatedUiRenderer $renderer,
    ) {}

    public function index(): mixed
    {
        $skills = Skill::withCount([
            'agents',
            'routingTags as routing_tags_count',
            'routingDepartments as routing_departments_count',
        ])
            ->orderBy('name')
            ->get();

        return $this->renderer->render('Escalated/Admin/Skills/Index', [
            'skills' => $skills->map(static fn (Skill $skill) => [
                'id' => $skill->id,
                'name' => $skill->name,
                'agents_count' => $skill->agents_count,
                'routing_tags_count' => $skill->routing_tags_count,
                'routing_departments_count' => $skill->routing_departments_count,
                'updated_at' => $skill->updated_at,
            ]),
        ]);
    }

    public function create(): mixed
    {
        return $this->renderer->render('Escalated/Admin/Skills/Form', $this->sharedFormOptions());
    }

    public function store(StoreSkillRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated): void {
            $skill = Skill::create(['name' => $validated['name']]);

            $skill->routingTags()->sync($validated['routing_tag_ids'] ?? []);
            $skill->routingDepartments()->sync($validated['routing_department_ids'] ?? []);
            $this->syncAgentsForSkill($skill, $validated['agents'] ?? []);
        });

        return redirect()->route('escalated.admin.skills.index')
            ->with('success', 'Skill created.');
    }

    public function edit(Skill $skill): mixed
    {
        $skill->load(['routingTags', 'routingDepartments', 'agentSkills']);

        $payload = array_merge($this->sharedFormOptions(), [
            'skill' => [
                'id' => $skill->id,
                'name' => $skill->name,
                'routing_tag_ids' => $skill->routingTags->pluck('id')->values()->all(),
                'routing_department_ids' => $skill->routingDepartments->pluck('id')->values()->all(),
                'agents' => $skill->agentSkills->map(static fn ($row) => [
                    'user_id' => $row->user_id,
                    'proficiency' => (int) $row->proficiency,
                ])->values()->all(),
            ],
        ]);

        return $this->renderer->render('Escalated/Admin/Skills/Form', $payload);
    }

    public function update(UpdateSkillRequest $request, Skill $skill): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $skill): void {
            $skill->update(['name' => $validated['name']]);

            $skill->routingTags()->sync($validated['routing_tag_ids'] ?? []);
            $skill->routingDepartments()->sync($validated['routing_department_ids'] ?? []);
            $this->syncAgentsForSkill($skill, $validated['agents'] ?? []);
        });

        return redirect()->route('escalated.admin.skills.index')
            ->with('success', 'Skill updated.');
    }

    public function destroy(Skill $skill): RedirectResponse
    {
        DB::transaction(function () use ($skill): void {
            $skill->agentSkills()->delete();
            $skill->delete();
        });

        return redirect()->route('escalated.admin.skills.index')
            ->with('success', 'Skill deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function sharedFormOptions(): array
    {
        $userModel = Escalated::userModel();
        /** @var Model $userProbe */
        $userProbe = new $userModel;
        $usersTable = $userProbe->getTable();
        $displayColumn = Escalated::userSearchableDisplayColumn();

        $agentsQuery = $userModel::query()->orderBy($displayColumn);

        if (Schema::hasColumn($usersTable, 'is_agent')) {
            $agentsQuery->where('is_agent', true);
        }

        return [
            'availableAgents' => $agentsQuery
                ->get()
                ->map(static fn ($user) => [
                    'id' => $user->getKey(),
                    'name' => $user->{$displayColumn} ?? $user->email,
                    'email' => $user->email,
                ])
                ->values()
                ->all(),
            'availableTags' => Tag::orderBy('name')
                ->get()
                ->map(static fn ($tag) => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                ])
                ->values()
                ->all(),
            'availableDepartments' => Department::orderBy('name')
                ->get()
                ->map(static fn ($dept) => [
                    'id' => $dept->id,
                    'name' => $dept->name,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<int, array{user_id: int, proficiency?: int}>  $agents
     */
    protected function syncAgentsForSkill(Skill $skill, array $agents): void
    {
        $payload = [];

        foreach ($agents as $row) {
            $userId = (int) ($row['user_id'] ?? 0);
            if ($userId <= 0) {
                continue;
            }

            $proficiency = isset($row['proficiency']) ? (int) $row['proficiency'] : 3;

            $payload[$userId] = [
                'proficiency' => $proficiency,
            ];
        }

        $skill->agents()->sync($payload);
    }
}
