<?php

namespace Escalated\Laravel\Console\Commands;

use Escalated\Laravel\Models\SlackInboundEvent;
use Escalated\Laravel\Services\SlackInboxProcessor;
use Illuminate\Console\Command;

class ProcessSlackInboxCommand extends Command
{
    protected $signature = 'escalated:slack:process {--limit=100 : Maximum due events} {--failed : List failed receipts without message bodies} {--retry= : Explicit failed receipt ID to retry}';

    protected $description = 'Process durably accepted Slack messages in the current tenant';

    public function handle(SlackInboxProcessor $processor): int
    {
        if (! config('escalated.slack.enabled', false) || config('escalated.mode', 'self-hosted') !== 'self-hosted') {
            $this->error('Enable self-hosted Slack inbound processing first.');

            return self::FAILURE;
        }
        if ($this->option('failed')) {
            $rows = SlackInboundEvent::where('status', 'failed')->orderBy('id')
                ->limit(max(1, min(1000, (int) $this->option('limit'))))
                ->get(['id', 'event_id', 'workspace_id', 'channel_id', 'last_error', 'attempts']);
            $this->table(['Receipt', 'Event', 'Workspace', 'Channel', 'Error', 'Attempts'], $rows->toArray());

            return self::SUCCESS;
        }
        if ($retry = $this->option('retry')) {
            $updated = SlackInboundEvent::whereKey($retry)->where('status', 'failed')
                ->update(['status' => 'pending', 'attempts' => 0, 'available_at' => now()]);
            if (! $updated) {
                $this->error('The failed receipt is not available in this tenant.');

                return self::FAILURE;
            }
        }
        $ids = SlackInboundEvent::where('status', 'pending')->where('available_at', '<=', now())
            ->orderBy('id')->limit(max(1, min(1000, (int) $this->option('limit'))))->pluck('id');
        $counts = [];
        foreach ($ids as $id) {
            $status = $processor->process($id);
            $counts[$status] = ($counts[$status] ?? 0) + 1;
        }
        $this->info('Slack inbox: '.json_encode($counts, JSON_THROW_ON_ERROR));

        return isset($counts['failed']) ? self::FAILURE : self::SUCCESS;
    }
}
