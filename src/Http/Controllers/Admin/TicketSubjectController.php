<?php

namespace Escalated\Laravel\Http\Controllers\Admin;

use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Models\TicketSubjectLink;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
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
        $validated = $request->validate([
            'type' => ['required', 'string'],
            'id' => ['required'],
            'role' => ['nullable', 'string', 'max:255'],
        ]);

        $class = $this->resolveAllowedModelClass($validated['type']);

        $subject = $class::query()->find($validated['id']);

        if ($subject === null) {
            throw ValidationException::withMessages([
                'id' => 'No matching subject was found.',
            ]);
        }

        $ticket->attachSubject($subject, $validated['role'] ?? null);

        return back();
    }

    public function destroy(Ticket $ticket, TicketSubjectLink $subject): RedirectResponse
    {
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
        $allowed = collect((array) config('escalated.ticket_subjects.types', []))
            ->flatMap(fn ($value, $key) => is_string($key) ? [$key, $value] : [$value])
            ->all();

        if (! in_array($type, $allowed, true)) {
            throw ValidationException::withMessages([
                'type' => "Subject type [{$type}] is not an allowed ticket subject.",
            ]);
        }

        $class = Relation::getMorphedModel($type) ?? $type;

        if (! is_string($class) || ! class_exists($class) || ! is_subclass_of($class, Model::class)) {
            throw ValidationException::withMessages([
                'type' => "Subject type [{$type}] could not be resolved to a model.",
            ]);
        }

        return $class;
    }
}
