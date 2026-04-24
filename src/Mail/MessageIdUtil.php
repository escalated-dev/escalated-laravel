<?php

namespace Escalated\Laravel\Mail;

/**
 * Pure helpers for RFC 5322 Message-ID threading and signed Reply-To
 * addresses. Mirrors the NestJS reference
 * `escalated-nestjs/src/services/email/message-id.ts` and the Spring /
 * WordPress / .NET / Phoenix ports.
 *
 * ## Message-ID format
 *   <ticket-{ticketId}@{domain}>             initial ticket email
 *   <ticket-{ticketId}-reply-{replyId}@{domain}>  agent reply
 *
 * ## Signed Reply-To format
 *   reply+{ticketId}.{hmac8}@{domain}
 *
 * The signed Reply-To carries ticket identity even when clients strip
 * our Message-ID / In-Reply-To headers — the inbound provider webhook
 * verifies the 8-char HMAC-SHA256 prefix before routing a reply to
 * its ticket.
 */
class MessageIdUtil
{
    /**
     * Build an RFC 5322 Message-ID. Pass `null` for `$replyId` on the
     * initial ticket email; the `-reply-{id}` tail is appended only
     * when `$replyId` is non-null.
     */
    public static function buildMessageId(int $ticketId, ?int $replyId, string $domain): string
    {
        $body = $replyId !== null
            ? sprintf('ticket-%d-reply-%d', $ticketId, $replyId)
            : sprintf('ticket-%d', $ticketId);

        return sprintf('<%s@%s>', $body, $domain);
    }

    /**
     * Extract the ticket id from a Message-ID we issued. Accepts the
     * header value with or without angle brackets. Returns `null` when
     * the input doesn't match our shape.
     */
    public static function parseTicketIdFromMessageId(?string $raw): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (preg_match('/ticket-(\d+)(?:-reply-\d+)?@/i', $raw, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /**
     * Build a signed Reply-To address of the form
     * `reply+{ticketId}.{hmac8}@{domain}`.
     */
    public static function buildReplyTo(int $ticketId, string $secret, string $domain): string
    {
        return sprintf('reply+%d.%s@%s', $ticketId, self::sign($ticketId, $secret), $domain);
    }

    /**
     * Verify a reply-to address (full `local@domain` or just the local
     * part). Returns the ticket id on match, `null` otherwise. Uses
     * `hash_equals` for timing-safe comparison.
     */
    public static function verifyReplyTo(?string $address, string $secret): ?int
    {
        if ($address === null || $address === '') {
            return null;
        }
        $at = strpos($address, '@');
        $local = $at !== false ? substr($address, 0, $at) : $address;
        if (! preg_match('/^reply\+(\d+)\.([a-f0-9]{8})$/i', $local, $m)) {
            return null;
        }
        $ticketId = (int) $m[1];
        $expected = self::sign($ticketId, $secret);

        return hash_equals(strtolower($expected), strtolower($m[2])) ? $ticketId : null;
    }

    /**
     * 8-character HMAC-SHA256 prefix over the ticket id.
     */
    private static function sign(int $ticketId, string $secret): string
    {
        return substr(hash_hmac('sha256', (string) $ticketId, $secret), 0, 8);
    }
}
