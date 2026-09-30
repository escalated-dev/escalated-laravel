<?php

namespace Escalated\Laravel\Http\Controllers;

use Escalated\Laravel\Models\SatisfactionRating;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Services\GuestAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SatisfactionRatingController extends Controller
{
    public function store(Ticket $ticket, Request $request): RedirectResponse
    {
        $this->authorizeRequester($ticket, $request);

        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
        ]);

        if (! in_array($ticket->status->value, ['resolved', 'closed'])) {
            return back()->with('error', __('escalated::messages.rating.only_resolved_closed'));
        }

        if ($ticket->satisfactionRating()->exists()) {
            return back()->with('error', __('escalated::messages.rating.already_rated'));
        }

        SatisfactionRating::create([
            'ticket_id' => $ticket->id,
            'rating' => $request->integer('rating'),
            'comment' => $request->input('comment'),
            'rated_by_type' => $request->user()?->getMorphClass(),
            'rated_by_id' => $request->user()?->getKey(),
        ]);

        return back()->with('success', __('escalated::messages.rating.thanks'));
    }

    public function storeGuest(string $token, Request $request): RedirectResponse
    {
        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
        ]);

        $ticket = app(GuestAccess::class)->resolve($token);

        if (! in_array($ticket->status->value, ['resolved', 'closed'])) {
            return back()->with('error', __('escalated::messages.rating.only_resolved_closed'));
        }

        if ($ticket->satisfactionRating()->exists()) {
            return back()->with('error', __('escalated::messages.rating.already_rated'));
        }

        SatisfactionRating::create([
            'ticket_id' => $ticket->id,
            'rating' => $request->integer('rating'),
            'comment' => $request->input('comment'),
        ]);

        return back()->with('success', __('escalated::messages.rating.thanks'));
    }

    /**
     * Only the ticket's own requester may rate it from the customer portal.
     */
    protected function authorizeRequester(Ticket $ticket, Request $request): void
    {
        $user = $request->user();

        if ($user === null
            || $ticket->requester_type !== $user->getMorphClass()
            || (string) $ticket->requester_id !== (string) $user->getKey()) {
            abort(403);
        }
    }
}
