<?php

namespace Escalated\Laravel\Http\Controllers\Api;

use Escalated\Laravel\Http\Resources\TicketSubjectResource;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Services\AgentTicketCreator;
use Escalated\Laravel\Services\TicketSubjectResolver;
use Escalated\Laravel\Services\TicketSubjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class TicketSubjectController extends Controller
{
    public function index(Ticket $ticket, Request $request): JsonResponse
    {
        Gate::forUser($request->user())->authorize('view', $ticket);
        AgentTicketCreator::assertSupported();

        return $this->response($ticket);
    }

    public function update(Ticket $ticket, Request $request): JsonResponse
    {
        Gate::forUser($request->user())->authorize('update', $ticket);
        AgentTicketCreator::assertSupported();
        $data = $request->validate(TicketSubjectResolver::rules(true));
        $subjects = app(TicketSubjectResolver::class)->resolveMany($data['subjects'], $request->user(), $ticket);
        app(TicketSubjectService::class)->replace($ticket, $subjects, $request->user());

        return $this->response($ticket);
    }

    private function response(Ticket $ticket): JsonResponse
    {
        $links = $ticket->subjects()->with('subject')->get()->each(fn ($link) => $link->setRelation('ticket', $ticket));

        return response()->json(['data' => TicketSubjectResource::collection($links)]);
    }
}
