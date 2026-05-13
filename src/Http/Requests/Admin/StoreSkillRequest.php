<?php

namespace Escalated\Laravel\Http\Requests\Admin;

use Escalated\Laravel\Escalated;
use Escalated\Laravel\Models\Department;
use Escalated\Laravel\Models\Skill;
use Escalated\Laravel\Models\Tag;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSkillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $agents = collect($this->input('agents', []))
            ->map(function (mixed $row) {
                if (! is_array($row)) {
                    return $row;
                }

                return array_merge(
                    ['proficiency' => 3],
                    $row,
                );
            })
            ->all();

        $this->merge([
            'agents' => $agents,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique(Skill::make()->getTable(), 'name')],
            'routing_tag_ids' => ['sometimes', 'array'],
            'routing_tag_ids.*' => ['integer', Rule::exists(Tag::make()->getTable(), 'id')],
            'routing_department_ids' => ['sometimes', 'array'],
            'routing_department_ids.*' => ['integer', Rule::exists(Department::make()->getTable(), 'id')],
            'agents' => ['sometimes', 'array'],
            'agents.*.user_id' => ['required_with:agents', 'integer', Rule::exists((new (Escalated::userModel()))->getTable(), 'id')],
            'agents.*.proficiency' => ['required_with:agents.*.user_id', 'integer', 'between:1,5'],
        ];
    }
}
