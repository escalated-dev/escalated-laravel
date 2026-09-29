<?php

namespace Escalated\Laravel\Services;

use Escalated\Laravel\Enums\TicketPriority;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Escalated;
use Escalated\Laravel\Models\Contact;
use Escalated\Laravel\Models\EscalatedSettings;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Tenancy\TenantContext;
use Illuminate\Support\Facades\Storage;

class GuestTicketService
{
    /** @return array{ticket: Ticket, token: string} */
    public function create(array $data, string $channel, array $files = []): array
    {
        $stored = [];
        $ticket = null;
        try {
            return app(GuestEmailVerification::class)->consume(
                $data['verification_id'], $data['verification_code'], $data['email'], 'ticket',
                function () use ($data, $channel, $files, &$stored, &$ticket) {
                    $email = GuestEmailVerification::email($data['email']);
                    $contact = Contact::findOrCreateByEmail($email, $data['name']);
                    $ticket = new Ticket([
                        'guest_name' => $data['name'], 'guest_email' => $email,
                        'guest_verified_email' => $email, 'guest_email_verified_at' => now(),
                        'contact_id' => $contact->id, 'subject' => $data['subject'],
                        'description' => $data['description'], 'status' => TicketStatus::Open,
                        'priority' => TicketPriority::from($data['priority'] ?? config('escalated.default_priority', 'medium')),
                        'channel' => $channel, 'department_id' => $data['department_id'] ?? null,
                    ]);
                    $ticket->deferCreatedEvent = true;
                    $this->applyIdentityPolicy($ticket);
                    $ticket->save();
                    foreach ($files as $file) {
                        $stored[] = app(AttachmentService::class)->store($ticket, $file);
                    }
                    $token = app(GuestAccess::class)->issue($ticket);
                    $ticket->dispatchCreatedAfterCommit();

                    return ['ticket' => $ticket, 'token' => $token];
                }
            );
        } catch (\Throwable $error) {
            // Compensate files only when the DB aggregate rolled back. A failed
            // after-commit listener must not remove committed attachments.
            if (! $ticket?->exists || ! Ticket::whereKey($ticket->getKey())->exists()) {
                foreach ($stored as $attachment) {
                    Storage::disk($attachment->disk)->delete($attachment->path);
                }
            }
            throw $error;
        }
    }

    public function applyIdentityPolicy(Ticket $ticket): void
    {
        if (EscalatedSettings::get('guest_policy_mode', 'unassigned') !== 'guest_user') {
            return;
        }
        $id = EscalatedSettings::get('guest_policy_user_id');
        if (! $id) {
            return;
        }
        $query = app(TenantContext::class)->scopeHost(Escalated::newUserModel()->newQuery());
        $user = $query->whereKey($id)->first();
        if ($user && app(TenantContext::class)->canAccess($user)) {
            $ticket->requester()->associate($user);
        }
    }
}
