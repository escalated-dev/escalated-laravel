<?php

namespace Escalated\Laravel\Services;

use Escalated\Laravel\Contracts\Ticketable;
use Escalated\Laravel\Enums\ActivityType;
use Escalated\Laravel\Enums\TicketPriority;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\EscalatedManager;
use Escalated\Laravel\Models\Reply;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Models\TicketLink;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class TicketService
{
    public function __construct(protected EscalatedManager $manager) {}

    public function create(Ticketable $requester, array $data): Ticket
    {
        return $this->manager->driver()->createTicket($requester, $data);
    }

    public function update(Ticket $ticket, array $data): Ticket
    {
        return $this->manager->driver()->updateTicket($ticket, $data);
    }

    public function changeStatus(Ticket $ticket, TicketStatus $status, ?Ticketable $causer = null): Ticket
    {
        return $this->manager->driver()->transitionStatus($ticket, $status, $causer);
    }

    public function reply(Ticket $ticket, Ticketable $author, string $body, array $attachments = []): Reply
    {
        return $this->manager->driver()->addReply($ticket, $author, $body, false, $attachments);
    }

    public function addNote(Ticket $ticket, Ticketable $author, string $body, array $attachments = []): Reply
    {
        return $this->manager->driver()->addReply($ticket, $author, $body, true, $attachments);
    }

    public function find(int|string $id): Ticket
    {
        return $this->manager->driver()->getTicket($id);
    }

    public function list(array $filters = [], ?Ticketable $for = null): LengthAwarePaginator
    {
        return $this->manager->driver()->listTickets($filters, $for);
    }

    public function changePriority(Ticket $ticket, TicketPriority $priority, ?Ticketable $causer = null): Ticket
    {
        return $this->manager->driver()->changePriority($ticket, $priority, $causer);
    }

    public function addTags(Ticket $ticket, array $tagIds, ?Ticketable $causer = null): Ticket
    {
        return $this->manager->driver()->addTags($ticket, $tagIds, $causer);
    }

    public function removeTags(Ticket $ticket, array $tagIds, ?Ticketable $causer = null): Ticket
    {
        return $this->manager->driver()->removeTags($ticket, $tagIds, $causer);
    }

    public function changeDepartment(Ticket $ticket, int $departmentId, ?Ticketable $causer = null): Ticket
    {
        return $this->manager->driver()->changeDepartment($ticket, $departmentId, $causer);
    }

    public function close(Ticket $ticket, ?Ticketable $causer = null): Ticket
    {
        return $this->changeStatus($ticket, TicketStatus::Closed, $causer);
    }

    public function resolve(Ticket $ticket, ?Ticketable $causer = null): Ticket
    {
        return $this->changeStatus($ticket, TicketStatus::Resolved, $causer);
    }

    public function reopen(Ticket $ticket, ?Ticketable $causer = null): Ticket
    {
        return $this->changeStatus($ticket, TicketStatus::Reopened, $causer);
    }

    /**
     * Split a reply from the source ticket into a new ticket.
     */
    public function splitTicket(Ticket $source, Reply $reply, array $data = []): Ticket
    {
        return DB::transaction(function () use ($source, $reply, $data) {
            $subject = $data['subject'] ?? 'Split from '.$source->reference.': '.$source->subject;

            $newTicket = Ticket::create([
                'subject' => $subject,
                'description' => $reply->body,
                'status' => TicketStatus::Open,
                'priority' => $source->priority,
                'requester_type' => $source->requester_type,
                'requester_id' => $source->requester_id,
                'department_id' => $source->department_id,
                'guest_name' => $source->guest_name,
                'guest_email' => $source->guest_email,
            ]);

            $tagIds = $source->tags()->pluck('id')->all();
            if ($tagIds) {
                $newTicket->tags()->sync($tagIds);
            }

            TicketLink::create([
                'parent_ticket_id' => $source->id,
                'child_ticket_id' => $newTicket->id,
                'link_type' => 'split',
            ]);

            Reply::create([
                'ticket_id' => $source->id,
                'body' => "Split to #{$newTicket->reference}",
                'is_internal_note' => true,
                'is_pinned' => false,
                'author_type' => null,
                'author_id' => null,
                'metadata' => [
                    'system_note' => true,
                    'split_to' => $newTicket->reference,
                ],
            ]);

            $source->logActivity(ActivityType::TicketSplit, null, [
                'split_to' => $newTicket->reference,
                'split_to_id' => $newTicket->id,
            ]);

            Reply::create([
                'ticket_id' => $newTicket->id,
                'body' => "Split from #{$source->reference}",
                'is_internal_note' => true,
                'is_pinned' => false,
                'author_type' => null,
                'author_id' => null,
                'metadata' => [
                    'system_note' => true,
                    'split_from' => $source->reference,
                ],
            ]);

            $newTicket->logActivity(ActivityType::TicketSplit, null, [
                'split_from' => $source->reference,
                'split_from_id' => $source->id,
            ]);

            return $newTicket->fresh();
        });
    }
}
