<?php

namespace Escalated\Laravel\Console\Commands;

use Escalated\Laravel\Models\ChatRoutingRule;
use Escalated\Laravel\Models\ChatSession;
use Escalated\Laravel\Services\ChatSessionService;
use Illuminate\Console\Command;

class CloseIdleChatsCommand extends Command
{
    protected $signature = 'escalated:close-idle-chats';

    protected $description = 'Close active chat sessions with no messages for X minutes';

    public function handle(ChatSessionService $chatSessionService): int
    {
        $defaultMinutes = 30;

        $sessions = ChatSession::active()->get();
        $closed = 0;

        foreach ($sessions as $session) {
            $rule = ChatRoutingRule::active()
                ->where(function ($q) use ($session) {
                    $q->where('department_id', $session->ticket->department_id)
                        ->orWhereNull('department_id');
                })
                ->ordered()
                ->first();

            $minutes = $rule?->auto_close_after_minutes ?? $defaultMinutes;

            $lastReply = $session->ticket->replies()
                ->where('is_internal_note', false)
                ->latest()
                ->first();

            $lastActivity = $lastReply?->created_at ?? $session->started_at;

            if ($lastActivity->diffInMinutes(now()) >= $minutes) {
                $chatSessionService->endChat($session, 'system');
                $closed++;
            }
        }

        $this->info("Closed {$closed} idle chat session(s).");

        return self::SUCCESS;
    }
}
