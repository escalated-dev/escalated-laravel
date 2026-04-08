<?php

namespace Escalated\Laravel\Console\Commands;

use Escalated\Laravel\Mail\Adapters\ImapAdapter;
use Escalated\Laravel\Models\EscalatedSettings;
use Escalated\Laravel\Services\InboundEmailService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class PollImapCommand extends Command
{
    protected $signature = 'escalated:poll-imap
        {--limit=50 : Maximum number of emails to process per run}
        {--dry-run : Parse and display emails without processing them}';

    protected $description = 'Poll the configured IMAP mailbox for new inbound emails';

    public function handle(InboundEmailService $service): int
    {
        if (! EscalatedSettings::getBool('inbound_email_enabled', (bool) config('escalated.inbound_email.enabled', false))) {
            $this->error('Inbound email is disabled. Enable it in admin settings or set ESCALATED_INBOUND_EMAIL=true in .env.');

            return self::FAILURE;
        }

        $adapter = EscalatedSettings::get('inbound_email_adapter', config('escalated.inbound_email.adapter', 'mailgun'));
        if ($adapter !== 'imap') {
            $this->warn("Inbound email adapter is not set to IMAP. Current adapter: {$adapter}");
        }

        $host = EscalatedSettings::get('imap_host', config('escalated.inbound_email.imap.host'));
        if (empty($host)) {
            $this->error('IMAP host is not configured. Set ESCALATED_IMAP_HOST in .env.');

            return self::FAILURE;
        }

        if (! function_exists('imap_open')) {
            $this->error('The IMAP PHP extension is not installed. Install php-imap to use this command.');

            return self::FAILURE;
        }

        $limit = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');

        $this->info("Connecting to IMAP server: {$host}...");

        try {
            $adapter = new ImapAdapter;
            $messages = $adapter->fetchMessages();
        } catch (\Throwable $e) {
            $this->error("Failed to fetch IMAP messages: {$e->getMessage()}");

            return self::FAILURE;
        }

        $count = count($messages);
        $this->info("Found {$count} unread message(s).");

        if ($count === 0) {
            return self::SUCCESS;
        }

        $processed = 0;
        $failed = 0;

        foreach (array_slice($messages, 0, $limit) as $message) {
            $label = Str::limit($message->subject, 50);

            if ($dryRun) {
                $this->line("  [DRY RUN] From: {$message->fromEmail} | Subject: {$label}");
                $processed++;

                continue;
            }

            try {
                $inboundEmail = $service->process($message, 'imap');

                $status = $inboundEmail->status;
                $ticketId = $inboundEmail->ticket_id;

                $this->line("  [{$status}] From: {$message->fromEmail} | Subject: {$label} | Ticket: {$ticketId}");

                if ($inboundEmail->isProcessed()) {
                    $processed++;
                } else {
                    $failed++;
                }
            } catch (\Throwable $e) {
                $this->error("  [ERROR] From: {$message->fromEmail} | {$e->getMessage()}");
                $failed++;
            }
        }

        $this->newLine();
        $this->info("Complete: {$processed} processed, {$failed} failed.");

        if ($count > $limit) {
            $remaining = $count - $limit;
            $this->warn("{$remaining} message(s) skipped due to --limit={$limit}. Run again to process more.");
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
