<?php

namespace Escalated\Laravel\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SnoozeTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'snoozed_until' => ['required', 'date', 'after:now'],
        ];
    }
}
