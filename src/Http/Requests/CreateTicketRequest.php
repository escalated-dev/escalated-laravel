<?php

namespace Escalated\Laravel\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxSize = config('escalated.tickets.max_attachment_size_kb', 10240);
        $maxFiles = config('escalated.tickets.max_attachments_per_reply', 5);

        return [
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'priority' => ['nullable', 'in:low,medium,high,urgent,critical'],
            'department_id' => ['nullable', 'exists:escalated_departments,id'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:'.$maxSize, 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,csv,txt,zip,mp4,mp3'],
        ];
    }
}
