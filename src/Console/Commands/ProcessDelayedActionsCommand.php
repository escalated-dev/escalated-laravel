<?php

namespace Escalated\Laravel\Console\Commands;

use Escalated\Laravel\Models\DelayedAction;
use Escalated\Laravel\Services\WorkflowEngine;
use Illuminate\Console\Command;

class ProcessDelayedActionsCommand extends Command
{
    protected $signature = 'escalated:process-delayed-actions';

    protected $description = 'Process due delayed workflow actions';

    public function handle(WorkflowEngine $engine): int
    {
        $dueActions = DelayedAction::due()
            ->with(['workflow', 'ticket'])
            ->get();

        $processed = 0;

        foreach ($dueActions as $delayedAction) {
            if (! $delayedAction->workflow || ! $delayedAction->ticket) {
                $delayedAction->update(['cancelled' => true]);

                continue;
            }

            // Re-evaluate conditions before executing
            $matched = $engine->evaluateConditions(
                $delayedAction->workflow->conditions ?? [],
                $delayedAction->ticket
            );

            if (! $matched) {
                $delayedAction->update(['cancelled' => true]);

                continue;
            }

            try {
                // Execute remaining actions (which may include further delays)
                $engine->executeActions(
                    $delayedAction->workflow,
                    $delayedAction->ticket,
                    $delayedAction->remaining_actions ?? []
                );

                $delayedAction->update(['executed' => true]);
                $processed++;
            } catch (\Throwable $e) {
                $this->error("Failed to process delayed action {$delayedAction->id}: {$e->getMessage()}");
                $delayedAction->update(['cancelled' => true]);
            }
        }

        $this->info("Processed {$processed} delayed actions.");

        return self::SUCCESS;
    }
}
