<?php

namespace Escalated\Laravel\Mail;

use Escalated\Laravel\Models\Ticket;
use Symfony\Component\Mime\Email;

/**
 * Helper that applies RFC 5322 threading headers + signed Reply-To to
 * a Symfony Mailer {@see Email} from within Notification
 * `withSymfonyMessage` callbacks.
 *
 * Centralizes the threading logic so every notification points replies
 * back to the canonical ticket thread and the signed Reply-To routes
 * inbound mail back to the correct ticket even when clients strip
 * Message-ID / In-Reply-To headers.
 *
 * Uses {@see MessageIdUtil} for the actual header building.
 *
 * ## Usage
 *
 * Initial ticket notification (sets the thread anchor):
 *
 *     ->withSymfonyMessage(fn ($message) =>
 *         NotificationThreading::applyAnchor($message, $ticket))
 *
 * Reply-to-thread notifications (SLA, assigned, status change, etc.):
 *
 *     ->withSymfonyMessage(fn ($message) =>
 *         NotificationThreading::applyThread($message, $ticket))
 *
 * Agent-reply notification (sets own Message-ID too):
 *
 *     ->withSymfonyMessage(fn ($message) =>
 *         NotificationThreading::applyThread($message, $ticket, $replyId))
 */
class NotificationThreading
{
    /**
     * Apply thread-anchor headers: set Message-ID to the ticket root.
     * Use on the initial ticket-created notification — this is the
     * anchor every subsequent message in the thread references.
     */
    public static function applyAnchor(Email $message, Ticket $ticket): void
    {
        $rootId = MessageIdUtil::buildMessageId((int) $ticket->id, null, self::domain());
        $message->getHeaders()->remove('Message-ID');
        // addIdHeader wraps the id in angle brackets for us.
        $message->getHeaders()->addIdHeader('Message-ID', self::stripAngles($rootId));

        self::applySignedReplyTo($message, $ticket);
    }

    /**
     * Apply thread-reply headers so the message joins the ticket's
     * thread. When `$replyId` is given, also sets the message's own
     * Message-ID to `<ticket-id-reply-replyId@domain>`.
     */
    public static function applyThread(Email $message, Ticket $ticket, ?int $replyId = null): void
    {
        $rootId = MessageIdUtil::buildMessageId((int) $ticket->id, null, self::domain());
        $headers = $message->getHeaders();

        $headers->addIdHeader('In-Reply-To', self::stripAngles($rootId));
        $headers->addIdHeader('References', self::stripAngles($rootId));

        if ($replyId !== null) {
            $messageId = MessageIdUtil::buildMessageId((int) $ticket->id, $replyId, self::domain());
            $headers->remove('Message-ID');
            $headers->addIdHeader('Message-ID', self::stripAngles($messageId));
        }

        self::applySignedReplyTo($message, $ticket);
    }

    /**
     * Set a signed Reply-To so the inbound provider webhook can verify
     * ticket identity without trusting client-side headers. Skipped
     * when `escalated.email.inbound_secret` is unset (empty default).
     */
    protected static function applySignedReplyTo(Email $message, Ticket $ticket): void
    {
        $secret = (string) config('escalated.email.inbound_secret', '');
        if ($secret === '') {
            return;
        }
        $replyTo = MessageIdUtil::buildReplyTo((int) $ticket->id, $secret, self::domain());
        $message->replyTo($replyTo);
    }

    protected static function domain(): string
    {
        $configured = (string) config('escalated.email.domain', '');
        if ($configured !== '') {
            return $configured;
        }
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        return $host ?: 'escalated.dev';
    }

    /**
     * Symfony's addIdHeader wraps the value in angle brackets itself,
     * so pass the bare `ticket-42@domain` part without the `<>`.
     */
    protected static function stripAngles(string $messageId): string
    {
        return trim($messageId, '<>');
    }
}
