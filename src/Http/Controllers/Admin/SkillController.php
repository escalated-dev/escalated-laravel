<?php

namespace Escalated\Laravel\Http\Controllers\Admin;

use Escalated\Laravel\Contracts\EscalatedUiRenderer;
use Escalated\Laravel\Escalated;
use Escalated\Laravel\Models\Department;
use Escalated\Laravel\Models\Skill;
use Escalated\Laravel\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class SkillController extends Controller
{
    public function __construct(
        protected EscalatedUiRenderer $renderer,
    ) {}

    public function index(): mixed
    {
        $skills = Skill::withCount('agents')
            ->orderBy('name')
            ->get()
            ->map(fn (Skill $skill) => [
                'id' => $skill->id,
                'name' => $skill->name,
                'slug' => $skill->slug,
                'agents_count' => $skill->agents_count,
                'routing_tags_count' => count($skill->routing_tag_ids ?? []),
                'routing_departments_count' => count($skill->routing_department_ids ?? []),
                'updated_at' => $skill->updated_at?->toIso8601String(),
            ]);

        return $this->renderer->render('Escalated/Admin/Skills/Index', [
            'skills' => $skills,
        ]);
    }

    public function create(): mixed
    {
        return $this->renderer->render('Escalated/Admin/Skills/Form', $this->formPayload());
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatePayload($request);

        DB::transaction(function () use ($validated): void {
            $skill = Skill::create([
                'name' => $validated['name'],
                'routing_tag_ids' => $validated['routing_tag_ids'] ?? [],
                'routing_department_ids' => $validated['routing_department_ids'] ?? [],
            ]);

            $this->syncAgents($skill, $validated['agents'] ?? []);
        });

        return redirect()->route('escalated.admin.skills.index')
            ->with('success', 'Skill created.');
    }

    public function edit(Skill $skill): mixed
    {
        $skill->load('agents');

        return $this->renderer->render('Escalated/Admin/Skills/Form', [
            ...$this->formPayload(),
            'skill' => [
                'id' => $skill->id,
                'name' => $skill->name,
                'slug' => $skill->slug,
                'routing_tag_ids' => $skill->routing_tag_ids ?? [],
                'routing_department_ids' => $skill->routing_department_ids ?? [],
                'agents' => $skill->agents
                    ->map(fn ($agent) => [
                        'user_id' => $agent->getKey(),
                        'proficiency' => (int) ($agent->pivot->proficiency ?? 1),
                    ])
                    ->values(),
            ],
        ]);
    }

    public function update(Request $request, Skill $skill): RedirectResponse
    {
        $validated = $this->validatePayload($request, $skill);

        DB::transaction(function () use ($skill, $validated): void {
            $skill->update([
                'name' => $validated['name'],
                'routing_tag_ids' => $validated['routing_tag_ids'] ?? [],
                'routing_department_ids' => $validated['routing_department_ids'] ?? [],
            ]);

            $this->syncAgents($skill, $validated['agents'] ?? []);
        });

        return redirect()->route('escalated.admin.skills.index')
            ->with('success', 'Skill updated.');
    }

    public function destroy(Skill $skill): RedirectResponse
    {
        $skill->delete();

        return redirect()->route('escalated.admin.skills.index')
            ->with('success', 'Skill deleted.');
    }

    protected function validatePayload(Request $request, ?Skill $skill = null): array
    {
        $userModel = Escalated::userModel();
        $userInstance = new $userModel;
        $userTable = $userInstance->getTable();
        $userKey = $userInstance->getKeyName();
        $roleColumns = $this->userRoleColumns($userTable);

        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique(Skill::make()->getTable(), 'name')->ignore($skill?->id)],
            'routing_tag_ids' => ['sometimes', 'array'],
            'routing_tag_ids.*' => ['integer', Rule::exists(Tag::make()->getTable(), 'id')],
            'routing_department_ids' => ['sometimes', 'array'],
            'routing_department_ids.*' => ['integer', Rule::exists(Department::make()->getTable(), 'id')],
            'agents' => ['sometimes', 'array'],
            'agents.*.user_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists($userTable, $userKey),
                $this->agentRoleRule($userModel, $userKey, $roleColumns),
            ],
            'agents.*.proficiency' => ['required', 'integer', 'between:1,5'],
        ]);
    }

    /**
     * Closure rule asserting the user_id refers to a user who qualifies as an
     * agent (`is_agent` or `is_admin` truthy on the host's User model). Skips
     * the check when neither column exists, matching formPayload()'s fallback.
     */
    protected function agentRoleRule(string $userModel, string $userKey, array $roleColumns): \Closure
    {
        return function ($attribute, $value, $fail) use ($userModel, $userKey, $roleColumns): void {
            if ($roleColumns === []) {
                return;
            }

            $user = $userModel::query()->where($userKey, $value)->first();
            if ($user === null) {
                return; // Rule::exists already reports this; avoid duplicate failure.
            }

            foreach ($roleColumns as $column) {
                if (! empty($user->{$column})) {
                    return;
                }
            }

            $fail('The selected '.$attribute.' is not an agent.');
        };
    }

    /**
     * Which user-role columns exist on the host's users table (intersection of
     * what we recognise and what is present). Matches formPayload()'s probe.
     */
    protected function userRoleColumns(string $userTable): array
    {
        $columns = Schema::getColumnListing($userTable);

        return array_values(array_intersect(['is_agent', 'is_admin'], $columns));
    }

    protected function syncAgents(Skill $skill, array $agents): void
    {
        $payload = collect($agents)
            ->mapWithKeys(fn (array $agent) => [
                (int) $agent['user_id'] => ['proficiency' => (int) $agent['proficiency']],
            ])
            ->all();

        $skill->agents()->sync($payload);
    }

    protected function formPayload(): array
    {
        $userModel = Escalated::userModel();
        $userInstance = new $userModel;
        $userTable = $userInstance->getTable();
        $userKey = $userInstance->getKeyName();

        $agentQuery = $userModel::query()->orderBy('name');
        $columns = Schema::getColumnListing($userTable);
        if (in_array('is_agent', $columns, true) || in_array('is_admin', $columns, true)) {
            $agentQuery->where(function ($query) use ($columns) {
                if (in_array('is_agent', $columns, true)) {
                    $query->orWhere('is_agent', true);
                }
                if (in_array('is_admin', $columns, true)) {
                    $query->orWhere('is_admin', true);
                }
            });
        }

        return [
            'availableAgents' => $agentQuery->get([$userKey, 'name', 'email'])
                ->map(fn ($agent) => [
                    'id' => $agent->getKey(),
                    'name' => $agent->name,
                    'email' => $agent->email,
                ])
                ->values(),
            'availableTags' => Tag::query()->orderBy('name')->get(['id', 'name', 'color']),
            'availableDepartments' => Department::query()->orderBy('name')->get(['id', 'name']),
        ];
    }
}
