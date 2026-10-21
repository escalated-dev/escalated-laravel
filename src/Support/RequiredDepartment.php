<?php

namespace Escalated\Laravel\Support;

use Escalated\Laravel\Models\Department;
use Escalated\Laravel\Models\Ticket;
use Illuminate\Validation\ValidationException;

/**
 * Enforces `escalated.tickets.require_department` when a ticket is created.
 *
 * Runs from Ticket's `creating` hook, so every creation path (web, API,
 * widget, guest, mobile, chat, inbound email, plugins) is covered. A ticket
 * with no department gets `escalated.tickets.default_department_id`; with no
 * usable default, creation fails with a validation error on `department_id`
 * (a 422 or a redirect back with errors on HTTP paths). Imports are exempt.
 */
final class RequiredDepartment
{
    public static function apply(Ticket $ticket): void
    {
        if (! config('escalated.tickets.require_department', false)
            || $ticket->department_id !== null
            || ImportContext::isImporting()) {
            return;
        }

        $defaultId = config('escalated.tickets.default_department_id');

        if ($defaultId !== null && $defaultId !== '' && Department::whereKey($defaultId)->exists()) {
            $ticket->department_id = (int) $defaultId;

            return;
        }

        throw ValidationException::withMessages([
            'department_id' => __('validation.required', ['attribute' => 'department']),
        ]);
    }
}
