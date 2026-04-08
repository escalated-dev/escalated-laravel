<?php

namespace Escalated\Laravel\Console\Commands;

use Escalated\Laravel\Enums\ChatSessionStatus;
use Escalated\Laravel\Enums\OfflineBehavior;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Models\ChatRoutingRule;
use Escalated\Laravel\Models\ChatSession;
use Escalated\Laravel\Services\ChatSessionService;
use Illuminate\Console\Command;

class CleanupAbandonedChatsCommand extends Command
{
    protected $signature = 'escalated:cleanup-abandoned-chats';

    protected $description = 'Handle waiting chat sessions older than 10 minutes with no agent';

    public function handle(ChatSessionService $chatSessionService): int
    {
        $sessions = ChatSession::waiting()
            ->where('started_at', '<', now()->subMinutes(10))
            ->whereNull('agent_id')
            ->get();

        $processed = 0;

        foreach ($sessions as $session) {
            $rule = ChatRoutingRule::active()
                ->where(function ($q) use ($session) {
                    $q->where('department_id', $session->ticket->department_id)
                        ->orWhereNull('department_id');
                })
                ->ordered()
                ->first();

            $behavior = $rule?->offline_behavior ?? OfflineBehavior::TicketFallback;

            match ($behavior) {
                OfflineBehavior::TicketFallback => $this->convertToTicket($session),
                OfflineBehavior::Queue => null, // keep waiting
                default => $chatSessionService->endChat($session, 'system'),
            };

            $processed++;
        }

        $this->info("Processed {$processed} abandoned chat session(s).");

        return self::SUCCESS;
    }

    protected function convertToTicket(ChatSession $session): void
    {
        $session->update([
            'status' => ChatSessionStatus::Ended,
            'ended_at' => now(),
        ]);

        $session->ticket->update([
            'status' => TicketStatus::Open,
        ]);
    }
}
