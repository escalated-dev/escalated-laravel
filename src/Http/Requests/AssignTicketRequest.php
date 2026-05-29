<?php

namespace Escalated\Laravel\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Accept both integer and string/UUID host-app user keys.
            'agent_id' => ['required'],
        ];
    }
}
