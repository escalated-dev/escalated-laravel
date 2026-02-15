<?php

namespace Escalated\Laravel\Http\Controllers;

use Escalated\Laravel\Models\SatisfactionRating;
use Escalated\Laravel\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SatisfactionRatingController extends Controller
{
    public function store(Ticket $ticket, Request $request): RedirectResponse
    {
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

        $ticket = Ticket::where('guest_token', $token)->firstOrFail();

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
}
