<?php

namespace Escalated\Laravel\Http\Requests;

use Escalated\Laravel\Models\BusinessSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'business_schedule_id' => ['nullable', 'integer', Rule::exists(BusinessSchedule::class, 'id')],
        ];
    }
}
