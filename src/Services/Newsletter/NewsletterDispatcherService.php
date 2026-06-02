<?php

namespace Escalated\Laravel\Services\Newsletter;

use Escalated\Laravel\Mail\NewsletterMail;
use Escalated\Laravel\Models\Newsletter\Newsletter;
use Escalated\Laravel\Models\Newsletter\NewsletterDelivery;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class NewsletterDispatcherService
{
    /** Retry backoff per attempt number (minutes): 1m, 5m, 30m. */
    private const BACKOFF_MINUTES = [1, 5, 30];

    public function dispatchBatch(): void
    {
        if (! config('escalated.enable_newsletters', false)) {
            return;
        }

        $this->reclaimStuckRows();

        $batchSize = (int) config('escalated.newsletters.batch_size', 50);

        // Enforce the per-minute send rate across however many ticks run in a
        // minute (a cache counter keyed by the current minute), independent of
        // batch size / cron frequency.
        $rateLimit = (int) config('escalated.newsletters.rate_limit_per_minute', 60);
        $minuteKey = 'escalated:newsletters:sent:'.now()->format('YmdHi');
        $sentThisMinute = (int) Cache::get($minuteKey, 0);
        $allowance = max(0, $rateLimit - $sentThisMinute);

        if ($allowance > 0) {
            $claimLimit = min($batchSize, $allowance);

            $ids = DB::transaction(function () use ($claimLimit) {
                $rowIds = NewsletterDelivery::query()
                    ->where('status', 'pending')
                    ->where(function ($q) {
                        $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now());
                    })
                    ->orderBy('id')
                    ->limit($claimLimit)
                    ->lockForUpdate()
                    ->pluck('id');

                if ($rowIds->isEmpty()) {
                    return collect();
                }

                NewsletterDelivery::whereIn('id', $rowIds)
                    ->update(['status' => 'queued', 'claimed_at' => now()]);

                return $rowIds;
            });

            if ($ids->isNotEmpty()) {
                Cache::put($minuteKey, $sentThisMinute + $ids->count(), now()->addMinutes(2));
            }

            foreach ($ids as $id) {
                $delivery = NewsletterDelivery::find($id);
                if ($delivery) {
                    $this->dispatchOne($delivery);
                }
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
                'next_attempt_at' => null,
            ]);
            Newsletter::where('id', $delivery->newsletter_id)->increment('summary_sent');
        } catch (Throwable $e) {
            Log::warning('Newsletter delivery failed', [
                'delivery_id' => $delivery->id,
                'error' => $e->getMessage(),
            ]);
            $next = $delivery->attempt_count + 1;
            $max = count(self::BACKOFF_MINUTES);
            if ($next >= $max) {
                $delivery->update([
                    'status' => 'failed',
                    'failure_reason' => $e->getMessage(),
                    'attempt_count' => $next,
                    'claimed_at' => null,
                    'next_attempt_at' => null,
                ]);
            } else {
                // Requeue with exponential backoff (1m, 5m, 30m) so the next
                // tick doesn't immediately retry a transient failure.
                $delivery->update([
                    'status' => 'pending',
                    'attempt_count' => $next,
                    'claimed_at' => null,
                    'next_attempt_at' => now()->addMinutes(self::BACKOFF_MINUTES[$next - 1]),
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
            // Evaluate the hard-bounce rate over the FIRST `threshold` terminal
            // deliveries only (spec: ">5% in the first 100"), not cumulatively —
            // so the gate is an early circuit-breaker, not a running average.
            $firstTerminal = NewsletterDelivery::where('newsletter_id', $n->id)
                ->whereIn('status', ['sent', 'bounced', 'complained', 'failed'])
                ->orderBy('id')
                ->limit($threshold)
                ->pluck('status');

            if ($firstTerminal->count() < $threshold) {
                return;
            }

            $bounced = $firstTerminal->filter(fn ($status) => $status === 'bounced')->count();
            if (($bounced / $threshold) >= $rate) {
                $n->update(['status' => 'paused']);
                Log::warning('Newsletter auto-paused due to high bounce rate', [
                    'newsletter_id' => $n->id, 'bounced' => $bounced, 'sampled' => $threshold,
                ]);
            }
        });
    }
}
