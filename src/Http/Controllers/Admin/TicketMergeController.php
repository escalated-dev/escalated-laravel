<?php

namespace Escalated\Laravel\Http\Controllers\Admin;

use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Services\TicketMergeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class TicketMergeController extends Controller
{
    public function __construct(
        protected TicketMergeService $mergeService,
    ) {}

    /**
     * Search for tickets to merge into.
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate(['q' => 'required|string|min:1']);

        $term = $request->input('q');

        $tickets = Ticket::where(function ($query) use ($term) {
            $query->where('reference', 'like', "%{$term}%")
                ->orWhere('subject', 'like', "%{$term}%");
        })
            ->whereNull('merged_into_id')
            ->limit(10)
            ->get(['id', 'reference', 'subject', 'status', 'created_at']);

        return response()->json(['tickets' => $tickets]);
    }

    /**
     * Merge the given ticket into a target ticket.
     */
    public function merge(Request $request, Ticket $ticket): RedirectResponse
    {
        $request->validate([
            'target_reference' => 'required|string',
        ]);

        $target = Ticket::where('reference', $request->input('target_reference'))
            ->whereNull('merged_into_id')
            ->firstOrFail();

        if ($target->id === $ticket->id) {
            return back()->with('error', 'Cannot merge a ticket into itself.');
        }

        $this->mergeService->merge($ticket, $target, $request->user()?->getKey());

        return back()->with('success', "Ticket merged into {$target->reference}.");
    }
}
