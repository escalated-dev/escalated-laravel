<?php

namespace Escalated\Laravel\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSavedViewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'filters' => ['required', 'array'],
            'is_shared' => ['boolean'],
            'is_default' => ['boolean'],
            'icon' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:50'],
        ];
    }
}
