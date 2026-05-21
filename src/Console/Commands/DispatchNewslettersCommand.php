<?php

namespace Escalated\Laravel\Console\Commands;

use Escalated\Laravel\Models\Newsletter\Newsletter;
use Escalated\Laravel\Services\Newsletter\NewsletterDispatcherService;
use Escalated\Laravel\Services\Newsletter\NewsletterPlannerService;
use Illuminate\Console\Command;

class DispatchNewslettersCommand extends Command
{
    protected $signature = 'escalated:newsletters:dispatch';

    protected $description = 'Plan scheduled newsletters whose time has come and dispatch a batch of pending deliveries.';

    public function handle(NewsletterPlannerService $planner, NewsletterDispatcherService $dispatcher): int
    {
        if (! config('escalated.enable_newsletters', false)) {
            $this->info('Newsletter feature disabled — skipping.');

            return self::SUCCESS;
        }

        $due = Newsletter::where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->get();

        foreach ($due as $newsletter) {
            $this->info("Planning newsletter #{$newsletter->id}");
            $planner->plan($newsletter);
        }

        $this->info('Dispatching batch…');
        $dispatcher->dispatchBatch();
        $this->info('Done.');

        return self::SUCCESS;
    }
}
