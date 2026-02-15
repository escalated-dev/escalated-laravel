<?php

namespace Escalated\Laravel\Http\Controllers;

use Escalated\Laravel\Enums\TicketPriority;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Http\Requests\BulkActionRequest;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Services\AssignmentService;
use Escalated\Laravel\Services\TicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;

class BulkActionController extends Controller
{
    public function __construct(
        protected TicketService $ticketService,
        protected AssignmentService $assignmentService,
    ) {}

    public function __invoke(BulkActionRequest $request): RedirectResponse
    {
        $ticketIds = $request->validated('ticket_ids');
        $action = $request->validated('action');
        $value = $request->validated('value');
        $causer = $request->user();
        $successCount = 0;

        $tickets = Ticket::whereIn('id', $ticketIds)->get();

        foreach ($tickets as $ticket) {
            try {
                match ($action) {
                    'status' => $this->ticketService->changeStatus($ticket, TicketStatus::from($value), $causer),
                    'priority' => $this->ticketService->changePriority($ticket, TicketPriority::from($value), $causer),
                    'assign' => $this->assignmentService->assign($ticket, (int) $value, $causer),
                    'tags' => $this->ticketService->addTags($ticket, (array) $value, $causer),
                    'department' => $this->ticketService->changeDepartment($ticket, (int) $value, $causer),
                    'delete' => $ticket->delete(),
                };
                $successCount++;
            } catch (\Throwable) {
                // Skip tickets that fail (e.g. invalid status transitions)
            }
        }

        return back()->with('success', __('escalated::messages.bulk.updated', ['count' => $successCount]));
    }
}
