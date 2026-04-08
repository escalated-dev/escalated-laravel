<?php

namespace Escalated\Laravel\Console\Commands;

use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Services\TicketService;
use Illuminate\Console\Command;

class WakeSnoozedTicketsCommand extends Command
{
    protected $signature = 'escalated:wake-snoozed-tickets';

    protected $description = 'Unsnooze tickets whose snooze period has expired';

    public function handle(TicketService $ticketService): int
    {
        $tickets = Ticket::whereNotNull('snoozed_until')
            ->where('snoozed_until', '<=', now())
            ->get();

        $count = 0;
        foreach ($tickets as $ticket) {
            $ticketService->unsnoozeTicket($ticket);
            $count++;
        }

        $this->info("Unsnoozed {$count} tickets.");

        return self::SUCCESS;
    }
}
