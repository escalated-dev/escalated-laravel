<?php

namespace Escalated\Laravel\Http\Requests;

use Escalated\Laravel\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ticket_ids' => ['required', 'array', 'min:1', 'max:50'],
            'ticket_ids.*' => ['integer', 'exists:'.Ticket::class.',id'],
            'action' => ['required', 'string', Rule::in(['status', 'priority', 'assign', 'tags', 'department', 'delete'])],
            'value' => ['required_unless:action,delete'],
        ];
    }
}
