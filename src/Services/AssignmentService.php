<?php

namespace Escalated\Laravel\Services;

use Escalated\Laravel\Contracts\Ticketable;
use Escalated\Laravel\EscalatedManager;
use Escalated\Laravel\Models\Department;
use Escalated\Laravel\Models\Ticket;

class AssignmentService
{
    public function __construct(
        protected EscalatedManager $manager,
        protected ?SkillRoutingService $skillRoutingService = null,
    ) {}

    public function assign(Ticket $ticket, int|string $agentId, ?Ticketable $causer = null): Ticket
    {
        return $this->manager->driver()->assignTicket($ticket, $agentId, $causer);
    }

    public function unassign(Ticket $ticket, ?Ticketable $causer = null): Ticket
    {
        return $this->manager->driver()->unassignTicket($ticket, $causer);
    }

    public function reassign(Ticket $ticket, int|string $agentId, ?Ticketable $causer = null): Ticket
    {
        return $this->manager->driver()->assignTicket($ticket, $agentId, $causer);
    }

    public function autoAssign(Ticket $ticket): ?Ticket
    {
        $skillRouting = $this->skillRoutingService ??= app(SkillRoutingService::class);
        $matchedAgent = $skillRouting->findMatchingAgents($ticket)->first();
        if ($matchedAgent) {
            return $this->assign($ticket, $matchedAgent->getKey());
        }

        if (! $ticket->department_id) {
            return null;
        }

        $department = Department::find($ticket->department_id);
        if (! $department) {
            return null;
        }

        $agents = $department->agents;
        if ($agents->isEmpty()) {
            return null;
        }

        $agentId = $agents->sortBy(function ($agent) {
            return Ticket::where('assigned_to', $agent->getKey())->open()->count();
        })->first()->getKey();

        return $this->assign($ticket, $agentId);
    }

    public function getAgentWorkload(int|string $agentId): array
    {
        return [
            'open' => Ticket::assignedTo($agentId)->open()->count(),
            'resolved_today' => Ticket::assignedTo($agentId)
                ->where('resolved_at', '>=', now()->startOfDay())
                ->count(),
            'sla_breached' => Ticket::assignedTo($agentId)->open()->breachedSla()->count(),
        ];
    }
}
