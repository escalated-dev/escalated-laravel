<?php

namespace Escalated\Laravel\Http\Controllers\Admin;

use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Models\TicketSubjectLink;
use Escalated\Laravel\Services\TicketSubjectResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Attach/detach host-app subject models on a ticket. Types are resolved
 * strictly against the `escalated.ticket_subjects.types` allowlist so request
 * input can never instantiate an arbitrary class.
 */
class TicketSubjectController extends Controller
{
    public function store(Ticket $ticket, Request $request): RedirectResponse
    {
        Gate::forUser($request->user())->authorize('update', $ticket);
        $validated = $request->validate([
            'type' => ['required', 'string', 'max:255'],
            'id' => ['required'],
            'role' => ['nullable', 'string', 'max:255'],
        ]);

        $subject = app(TicketSubjectResolver::class)->resolve($validated, $request->user(), $ticket, '');

        $ticket->attachSubject($subject, $validated['role'] ?? null);

        return back();
    }

    public function destroy(Ticket $ticket, TicketSubjectLink $subject): RedirectResponse
    {
        Gate::forUser(request()->user())->authorize('update', $ticket);
        abort_unless((int) $subject->ticket_id === (int) $ticket->getKey(), 404);

        $subject->delete();

        return back();
    }

    /**
     * Resolve a request-supplied morph type to a model class, but only if it's
     * in the configured allowlist. Throws a 422 otherwise.
     */
    protected function resolveAllowedModelClass(string $type): string
    {
        $class = app(TicketSubjectResolver::class)->allowedTypes()[$type] ?? null;
        if (! $class) {
            throw ValidationException::withMessages([
                'type' => "Subject type [{$type}] is not an allowed ticket subject.",
            ]);
        }

        return $class;
    }
}
