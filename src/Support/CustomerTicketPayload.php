<?php

namespace Escalated\Laravel\Support;

use Escalated\Laravel\Models\Attachment;
use Escalated\Laravel\Models\Reply;
use Escalated\Laravel\Models\Ticket;

/**
 * Allow-listed ticket fields for requester-facing views (guest web page,
 * mobile API). Serializing the Ticket model directly would carry every
 * column and loaded relation — metadata, external_reference,
 * chat_metadata, assignee and author user records — to the requester.
 *
 * Staff are shown by display name only; the requester sees their own
 * name and email.
 */
class CustomerTicketPayload
{
    /**
     * Props for the `Escalated/Guest/Show` page.
     */
    public static function guestWeb(Ticket $ticket): array
    {
        $rating = $ticket->relationLoaded('satisfactionRating') ? $ticket->satisfactionRating : null;

        return [
            'reference' => $ticket->reference,
            'subject' => $ticket->subject,
            'description' => $ticket->description ?? '',
            'status' => $ticket->status->value,
            'priority' => $ticket->priority->value,
            'channel' => $ticket->channel?->value,
            'department' => $ticket->department ? ['name' => $ticket->department->name] : null,
            'attachments' => $ticket->relationLoaded('attachments')
                ? $ticket->attachments->map(fn (Attachment $attachment) => self::attachment($attachment))->values()->all()
                : [],
            'replies' => $ticket->relationLoaded('replies')
                ? $ticket->replies
                    ->reject(fn (Reply $reply) => (bool) $reply->is_internal_note)
                    ->map(fn (Reply $reply) => [
                        'id' => $reply->id,
                        'body' => $reply->body,
                        'is_internal_note' => false,
                        'is_pinned' => (bool) ($reply->is_pinned ?? false),
                        'author' => ['name' => self::replyAuthor($ticket, $reply)['name']],
                        'attachments' => $reply->relationLoaded('attachments')
                            ? $reply->attachments->map(fn (Attachment $attachment) => self::attachment($attachment))->values()->all()
                            : [],
                        'created_at' => $reply->created_at?->toIso8601String(),
                    ])->values()->all()
                : [],
            'satisfaction_rating' => $rating ? ['rating' => $rating->rating, 'comment' => $rating->comment] : null,
            'guest_access_expires_at' => $ticket->guest_access_expires_at?->toIso8601String(),
            'resolved_at' => $ticket->resolved_at?->toIso8601String(),
            'closed_at' => $ticket->closed_at?->toIso8601String(),
            'created_at' => $ticket->created_at?->toIso8601String(),
            'updated_at' => $ticket->updated_at?->toIso8601String(),
        ];
    }

    /**
     * The requester as the requester sees themselves.
     *
     * @return array{name: string, email: string}
     */
    public static function requester(Ticket $ticket): array
    {
        if ($ticket->guest_email !== null && $ticket->guest_email !== '') {
            return ['name' => $ticket->guest_name ?? 'Guest', 'email' => $ticket->guest_email];
        }

        return ['name' => $ticket->requester_name, 'email' => $ticket->requester_email];
    }

    /**
     * A reply author as the requester may see them. The requester's own
     * replies keep their identity; anyone else (staff) is reduced to a
     * display name with no id or email.
     *
     * @return array{id: int|string, name: string, email: string}
     */
    public static function replyAuthor(Ticket $ticket, Reply $reply): array
    {
        if ($reply->author_type === null) {
            return ['id' => 0] + self::requester($ticket);
        }

        $author = $reply->author;
        if ($ticket->requester_type !== null
            && $reply->author_type === $ticket->requester_type
            && (string) $reply->author_id === (string) $ticket->requester_id) {
            return [
                'id' => $author?->getKey() ?? 0,
                'name' => self::displayName($author) ?? $ticket->requester_name,
                'email' => (string) ($author?->email ?? ''),
            ];
        }

        return ['id' => 0, 'name' => self::displayName($author) ?? 'Support', 'email' => ''];
    }

    /**
     * An assigned agent reduced to a display name.
     *
     * @return array{id: int, name: string, email: string}|null
     */
    public static function assignee(Ticket $ticket): ?array
    {
        $assignee = $ticket->assignee;

        return $assignee ? ['id' => 0, 'name' => self::displayName($assignee) ?? 'Support', 'email' => ''] : null;
    }

    public static function attachment(Attachment $attachment): array
    {
        return [
            'id' => $attachment->id,
            'original_filename' => $attachment->original_filename,
            'mime_type' => $attachment->mime_type,
            'size' => $attachment->size,
            'url' => $attachment->url,
        ];
    }

    protected static function displayName(mixed $user): ?string
    {
        if ($user === null) {
            return null;
        }

        $name = $user->name ?? null;

        return is_string($name) && $name !== '' ? $name : null;
    }
}
