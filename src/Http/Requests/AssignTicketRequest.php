<?php

namespace Escalated\Laravel\Http\Requests;

use Escalated\Laravel\Escalated;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Accept both integer and string/UUID host-app user keys, but still
        // validate the agent exists so bad input fails with a clean 422 rather
        // than a 500 from the assignment path (mirrors Api\TicketController).
        $userModel = Escalated::newUserModel();

        return [
            'agent_id' => ['required', Rule::exists($userModel->getTable(), $userModel->getKeyName())],
        ];
    }
}
