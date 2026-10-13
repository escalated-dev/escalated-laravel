<?php

namespace Escalated\Laravel\Http\Requests;

use Escalated\Laravel\Models\Department;
use Escalated\Laravel\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSlaPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_default' => ['boolean'],
            'first_response_hours' => ['required', 'array'],
            'resolution_hours' => ['required', 'array'],
            'business_hours_only' => ['boolean'],
            'is_active' => ['boolean'],
            'department_id' => ['nullable', 'integer', Rule::exists(Department::class, 'id')],
            'ticket_type' => ['nullable', 'string', Rule::in(Ticket::TYPES)],
        ];
    }
}
