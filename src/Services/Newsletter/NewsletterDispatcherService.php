<?php

namespace Escalated\Laravel\Services\Newsletter;

use Escalated\Laravel\Mail\NewsletterMail;
use Escalated\Laravel\Models\Newsletter\Newsletter;
use Escalated\Laravel\Models\Newsletter\NewsletterDelivery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class NewsletterDispatcherService
{
    public function dispatchBatch(): void
    {
        if (! config('escalated.enable_newsletters', false)) {
            return;
        }

        $this->reclaimStuckRows();

        $batchSize = (int) config('escalated.newsletters.batch_size', 50);

        $ids = DB::transaction(function () use ($batchSize) {
            $rowIds = NewsletterDelivery::query()
                ->where('status', 'pending')
                ->orderBy('id')
                ->limit($batchSize)
                ->lockForUpdate()
                ->pluck('id');

            if ($rowIds->isEmpty()) {
                return collect();
            }

            NewsletterDelivery::whereIn('id', $rowIds)
                ->update(['status' => 'queued', 'claimed_at' => now()]);

            return $rowIds;
        });

        foreach ($ids as $id) {
            $delivery = NewsletterDelivery::find($id);
            if ($delivery) {
                $this->dispatchOne($delivery);
            }
        }

        $this->finalizeCompletedNewsletters();
        $this->checkAutoPauseAcrossActiveNewsletters();
    }

    private function dispatchOne(NewsletterDelivery $delivery): void
    {
        try {
            Mail::send(new NewsletterMail($delivery));
            $delivery->update([
                'status' => 'sent',
                'sent_at' => now(),
                'claimed_at' => null,
            ]);
            Newsletter::where('id', $delivery->newsletter_id)->increment('summary_sent');
        } catch (Throwable $e) {
            Log::warning('Newsletter delivery failed', [
                'delivery_id' => $delivery->id,
                'error' => $e->getMessage(),
            ]);
            $next = $delivery->attempt_count + 1;
            $max = 3;
            if ($next >= $max) {
                $delivery->update([
                    'status' => 'failed',
                    'failure_reason' => $e->getMessage(),
                    'attempt_count' => $next,
                    'claimed_at' => null,
                ]);
            } else {
                $delivery->update([
                    'status' => 'pending',
                    'attempt_count' => $next,
                    'claimed_at' => null,
                ]);
            }
        }
    }

    private function reclaimStuckRows(): void
    {
        $cutoff = now()->subMinutes((int) config('escalated.newsletters.claim_timeout_minutes', 10));
        NewsletterDelivery::where('status', 'queued')
            ->where('claimed_at', '<', $cutoff)
            ->update(['status' => 'pending', 'claimed_at' => null]);
    }

    private function finalizeCompletedNewsletters(): void
    {
        Newsletter::where('status', 'sending')->get()->each(function (Newsletter $n) {
            $remaining = NewsletterDelivery::where('newsletter_id', $n->id)
                ->whereIn('status', ['pending', 'queued'])
                ->exists();
            if (! $remaining) {
                $n->update(['status' => 'sent', 'sent_at' => $n->sent_at ?? now()]);
            }
        });
    }

    private function checkAutoPauseAcrossActiveNewsletters(): void
    {
        $threshold = (int) config('escalated.newsletters.auto_pause_threshold', 100);
        $rate = (float) config('escalated.newsletters.auto_pause_bounce_rate', 0.05);

        Newsletter::where('status', 'sending')->get()->each(function (Newsletter $n) use ($threshold, $rate) {
            $total = NewsletterDelivery::where('newsletter_id', $n->id)
                ->whereIn('status', ['sent', 'bounced', 'complained', 'failed'])
                ->count();
            if ($total < $threshold) {
                return;
            }
            $bounced = NewsletterDelivery::where('newsletter_id', $n->id)->where('status', 'bounced')->count();
            if ($total > 0 && ($bounced / $total) >= $rate) {
                $n->update(['status' => 'paused']);
                Log::warning('Newsletter auto-paused due to high bounce rate', [
                    'newsletter_id' => $n->id, 'bounced' => $bounced, 'total' => $total,
                ]);
            }
        });
    }
}
