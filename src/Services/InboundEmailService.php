<?php

namespace Escalated\Laravel\Services;

use Escalated\Laravel\Contracts\Ticketable;
use Escalated\Laravel\Enums\TicketPriority;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Escalated;
use Escalated\Laravel\Events;
use Escalated\Laravel\Mail\InboundMessage;
use Escalated\Laravel\Models\Attachment;
use Escalated\Laravel\Models\EscalatedSettings;
use Escalated\Laravel\Models\InboundEmail;
use Escalated\Laravel\Models\Reply;
use Escalated\Laravel\Models\Ticket;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InboundEmailService
{
    /**
     * Allowed HTML tags for inbound email body sanitization.
     */
    protected const ALLOWED_TAGS = '<p><br><b><strong><i><em><u><a><ul><ol><li><h1><h2><h3><h4><h5><h6><blockquote><pre><code><table><thead><tbody><tr><th><td><img><hr><div><span><sub><sup>';

    public function __construct(
        protected TicketService $ticketService,
        protected AttachmentService $attachmentService,
    ) {}

    /**
     * Process a normalized inbound email message.
     * Called by all adapters after normalizing their payload.
     */
    public function process(InboundMessage $message, string $adapter = 'unknown'): InboundEmail
    {
        // 1. Log the inbound email
        $inboundEmail = $this->logInboundEmail($message, $adapter);

        try {
            // Skip SNS subscription confirmations
            if ($message->fromEmail === 'sns-confirmation@amazonaws.com') {
                $inboundEmail->markProcessed();

                return $inboundEmail;
            }

            // Check for duplicate message ID
            if ($message->messageId && $this->isDuplicate($message->messageId, $inboundEmail->id)) {
                $inboundEmail->markProcessed();
                Log::info('Escalated: Duplicate inbound email skipped.', ['message_id' => $message->messageId]);

                return $inboundEmail;
            }

            // 2. Check if this is a reply to an existing ticket
            $existingTicket = $this->findTicketByEmail($message);

            // 3. Look up the sender
            $user = $this->findUserByEmail($message->fromEmail);

            if ($existingTicket) {
                // 4. Reply to existing ticket
                $reply = $this->addReplyToTicket($existingTicket, $message, $user);
                $inboundEmail->markProcessed($existingTicket->id, $reply->id);
            } else {
                // 5. Create new ticket
                $ticket = $this->createNewTicket($message, $user);
                $inboundEmail->markProcessed($ticket->id);
            }

            return $inboundEmail;
        } catch (\Throwable $e) {
            $inboundEmail->markFailed($e->getMessage());
            Log::error('Escalated: Failed to process inbound email.', [
                'inbound_email_id' => $inboundEmail->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $inboundEmail;
        }
    }

    /**
     * Find an existing ticket that this email is replying to.
     *
     * Checks:
     * 1. Subject line for ticket reference pattern like [ESC-00001]
     * 2. In-Reply-To / References headers for matching message IDs
     */
    protected function findTicketByEmail(InboundMessage $message): ?Ticket
    {
        // Check subject for reference pattern
        $prefix = EscalatedSettings::get('ticket_reference_prefix', 'ESC');
        $pattern = '/\[('.preg_quote($prefix, '/').'-\d+)\]/';

        if (preg_match($pattern, $message->subject, $matches)) {
            $ticket = Ticket::where('reference', $matches[1])->first();
            if ($ticket) {
                return $ticket;
            }
        }

        // Check In-Reply-To and References headers against stored message IDs
        $headerMessageIds = [];

        if (! empty($message->inReplyTo)) {
            $headerMessageIds[] = $message->inReplyTo;
        }

        if (! empty($message->references)) {
            // References header can contain multiple message IDs separated by whitespace
            $refs = preg_split('/\s+/', $message->references);
            $headerMessageIds = array_merge($headerMessageIds, $refs);
        }

        if (! empty($headerMessageIds)) {
            $relatedEmail = InboundEmail::whereIn('message_id', $headerMessageIds)
                ->whereNotNull('ticket_id')
                ->where('status', 'processed')
                ->latest()
                ->first();

            if ($relatedEmail) {
                return Ticket::find($relatedEmail->ticket_id);
            }
        }

        return null;
    }

    /**
     * Find an Eloquent user by email address using the configured user model.
     */
    protected function findUserByEmail(string $email): ?Ticketable
    {
        $userModelClass = Escalated::userModel();

        try {
            $user = $userModelClass::where('email', $email)->first();

            if ($user && $user instanceof Ticketable) {
                return $user;
            }
        } catch (\Throwable $e) {
            Log::warning('Escalated: Failed to look up user by email.', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Add a reply to an existing ticket from an inbound email.
     */
    protected function addReplyToTicket(Ticket $ticket, InboundMessage $message, ?Ticketable $user): Reply
    {
        $body = $this->getSanitizedBody($message);

        if ($user) {
            // Authenticated user reply — use the TicketService so events fire properly
            $reply = $this->ticketService->reply($ticket, $user, $body);
        } else {
            // Guest reply — create directly like GuestTicketController does
            $reply = new Reply;
            $reply->ticket_id = $ticket->id;
            $reply->author_type = null;
            $reply->author_id = null;
            $reply->body = $body;
            $reply->is_internal_note = false;
            $reply->type = 'reply';
            $reply->save();

            // ReplyCreated event is automatically dispatched by Reply::booted()
        }

        // Handle attachments
        $this->storeInboundAttachments($reply, $message->attachments);

        // Reopen ticket if it was resolved/closed
        if (in_array($ticket->status, [TicketStatus::Resolved, TicketStatus::Closed])) {
            try {
                $this->ticketService->reopen($ticket, $user);
            } catch (\InvalidArgumentException $e) {
                // Status transition not allowed — leave as-is
                Log::info('Escalated: Could not reopen ticket from inbound email reply.', [
                    'ticket_id' => $ticket->id,
                    'status' => $ticket->status->value,
                ]);
            }
        }

        return $reply;
    }

    /**
     * Create a new ticket from an inbound email.
     */
    protected function createNewTicket(InboundMessage $message, ?Ticketable $user): Ticket
    {
        $body = $this->getSanitizedBody($message);

        if ($user) {
            // Authenticated user — use TicketService so events and activity logging fire
            return $this->ticketService->create($user, [
                'subject' => $this->sanitizeSubject($message->subject),
                'description' => $body,
                'priority' => config('escalated.default_priority', 'medium'),
                'channel' => 'email',
            ]);
        }

        // Guest ticket — follow GuestTicketController pattern
        $ticket = Ticket::create([
            // Reference property is auto-generated by creating / created hook
            'guest_name' => $message->fromName ?? $this->nameFromEmail($message->fromEmail),
            'guest_email' => $message->fromEmail,
            'guest_token' => Str::random(64),
            'subject' => $this->sanitizeSubject($message->subject),
            'description' => $body,
            'status' => TicketStatus::Open,
            'priority' => TicketPriority::from(config('escalated.default_priority', 'medium')),
            'channel' => 'email',
        ]);

        // Handle attachments
        $this->storeInboundAttachments($ticket, $message->attachments);

        // TicketCreated event is automatically dispatched by the Ticket model's $dispatchesEvents property

        return $ticket;
    }

    /**
     * Log the inbound email to the database for audit trail.
     */
    protected function logInboundEmail(InboundMessage $message, string $adapter): InboundEmail
    {
        return InboundEmail::create([
            'message_id' => $message->messageId,
            'from_email' => $message->fromEmail,
            'from_name' => $message->fromName,
            'to_email' => $message->toEmail,
            'subject' => $message->subject,
            'body_text' => $message->bodyText,
            'body_html' => $this->sanitizeHtml($message->bodyHtml),
            'raw_headers' => $message->getRawHeadersString(),
            'status' => 'pending',
            'adapter' => $adapter,
        ]);
    }

    /**
     * Check if an email with this message ID has already been processed.
     */
    protected function isDuplicate(string $messageId, int $excludeId): bool
    {
        return InboundEmail::where('message_id', $messageId)
            ->where('id', '!=', $excludeId)
            ->where('status', 'processed')
            ->exists();
    }

    /**
     * Dangerous file extensions that should never be accepted from inbound emails.
     */
    protected const BLOCKED_EXTENSIONS = [
        'exe', 'bat', 'cmd', 'com', 'msi', 'scr', 'pif', 'vbs', 'vbe',
        'js', 'jse', 'wsf', 'wsh', 'ps1', 'psm1', 'psd1', 'reg',
        'cpl', 'hta', 'inf', 'lnk', 'sct', 'shb', 'sys', 'drv',
        'php', 'phtml', 'php3', 'php4', 'php5', 'phar',
        'sh', 'bash', 'csh', 'ksh', 'pl', 'py', 'rb',
        'dll', 'so', 'dylib',
    ];

    /**
     * Store email attachments from raw content to disk and create Attachment records.
     */
    protected function storeInboundAttachments($attachable, array $attachments): void
    {
        if (empty($attachments)) {
            return;
        }

        $disk = config('escalated.storage.disk', 'public');
        $basePath = config('escalated.storage.path', 'escalated/attachments');
        $maxSize = config('escalated.tickets.max_attachment_size_kb', 10240) * 1024;
        $maxCount = config('escalated.tickets.max_attachments_per_reply', 5);

        $stored = 0;

        foreach ($attachments as $attachment) {
            if ($stored >= $maxCount) {
                break;
            }

            $content = $attachment['content'] ?? '';
            $size = $attachment['size'] ?? strlen($content);

            // Skip oversized attachments
            if ($size > $maxSize) {
                Log::info('Escalated: Skipped oversized inbound attachment.', [
                    'filename' => $attachment['filename'] ?? 'unknown',
                    'size' => $size,
                    'max' => $maxSize,
                ]);

                continue;
            }

            $extension = strtolower(pathinfo($attachment['filename'] ?? '', PATHINFO_EXTENSION) ?: 'bin');

            // Block dangerous file extensions
            if (in_array($extension, self::BLOCKED_EXTENSIONS, true)) {
                Log::info('Escalated: Blocked dangerous inbound attachment.', [
                    'filename' => $attachment['filename'] ?? 'unknown',
                    'extension' => $extension,
                ]);

                continue;
            }

            $filename = Str::uuid().'.'.$extension;
            $path = $basePath.'/'.$filename;

            Storage::disk($disk)->put($path, $content);

            Attachment::create([
                'attachable_type' => $attachable->getMorphClass(),
                'attachable_id' => $attachable->getKey(),
                'filename' => $filename,
                'original_filename' => $attachment['filename'] ?? 'attachment',
                'mime_type' => $attachment['contentType'] ?? 'application/octet-stream',
                'size' => $size,
                'disk' => $disk,
                'path' => $path,
            ]);

            $stored++;
        }
    }

    /**
     * Sanitize the email subject for use as a ticket subject.
     * Removes common prefixes like RE:, FW:, etc.
     */
    protected function sanitizeSubject(string $subject): string
    {
        // Remove common reply/forward prefixes (may be stacked: FW: RE: ...)
        $cleaned = trim($subject);
        while (preg_match('/^(RE|FW|FWD)\s*:\s*/i', $cleaned)) {
            $cleaned = preg_replace('/^(RE|FW|FWD)\s*:\s*/i', '', $cleaned);
        }

        // Remove ticket reference brackets if present (will be re-added)
        $prefix = EscalatedSettings::get('ticket_reference_prefix', 'ESC');
        $cleaned = preg_replace('/\['.preg_quote($prefix, '/').'-\d+\]\s*/', '', $cleaned);

        return trim($cleaned) ?: '(No Subject)';
    }

    /**
     * Sanitize HTML content from inbound emails to prevent XSS.
     * Strips dangerous tags/attributes while keeping safe formatting.
     */
    protected function sanitizeHtml(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return $html;
        }

        // Strip all tags except allowed safe formatting tags
        $clean = strip_tags($html, self::ALLOWED_TAGS);

        // Remove event handler attributes (onclick, onerror, onload, etc.)
        $clean = preg_replace('/\s+on\w+\s*=\s*["\'][^"\']*["\']/i', '', $clean);
        $clean = preg_replace('/\s+on\w+\s*=\s*\S+/i', '', $clean);

        // Remove javascript: protocol from href/src/action attributes
        $clean = preg_replace('/\b(href|src|action)\s*=\s*["\']?\s*javascript\s*:/i', '$1="', $clean);

        // Remove data: URLs with script content (allow data:image)
        $clean = preg_replace('/\b(href|src|action)\s*=\s*["\']?\s*data\s*:(?!image\/)/i', '$1="', $clean);

        // Remove style attributes containing expression() or url(javascript:)
        $clean = preg_replace('/style\s*=\s*["\'][^"\']*expression\s*\([^"\']*["\']/i', '', $clean);
        $clean = preg_replace('/style\s*=\s*["\'][^"\']*url\s*\(\s*["\']?\s*javascript:[^"\']*["\']/i', '', $clean);

        return $clean;
    }

    /**
     * Get sanitized body content from an inbound message.
     * Prefers text body over HTML for safety; sanitizes HTML if used.
     */
    protected function getSanitizedBody(InboundMessage $message): string
    {
        // Prefer plain text — no XSS risk
        if (! empty($message->bodyText)) {
            return $message->bodyText;
        }

        // Fall back to sanitized HTML
        if (! empty($message->bodyHtml)) {
            return $this->sanitizeHtml($message->bodyHtml) ?? '';
        }

        return '';
    }

    /**
     * Derive a display name from an email address.
     */
    protected function nameFromEmail(string $email): string
    {
        $local = Str::before($email, '@');

        // Convert common separators to spaces and capitalize
        return Str::title(str_replace(['.', '_', '-', '+'], ' ', $local));
    }
}
