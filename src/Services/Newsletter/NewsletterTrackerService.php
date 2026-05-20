<?php

namespace Escalated\Laravel\Services\Newsletter;

use Escalated\Laravel\Models\Newsletter\Newsletter;
use Escalated\Laravel\Models\Newsletter\NewsletterDelivery;

class NewsletterTrackerService
{
    public function __construct(private readonly BounceSuppressionStore $bounces) {}

    public function recordOpen(string $token): void
    {
        $d = $this->findByToken($token);
        if (! $d) {
            return;
        }
        if (in_array($d->status, ['bounced', 'complained', 'failed'], true)) {
            return;
        }

        if ($d->opened_at === null) {
            $d->update(['opened_at' => now()]);
            Newsletter::where('id', $d->newsletter_id)->increment('summary_opened');
        }
    }

    public function recordClick(string $token, string $url): void
    {
        $d = $this->findByToken($token);
        if (! $d) {
            return;
        }
        if (in_array($d->status, ['bounced', 'complained', 'failed'], true)) {
            return;
        }

        $isFirstClick = $d->clicks_count === 0;
        $d->update([
            'clicks_count' => $d->clicks_count + 1,
            'last_clicked_at' => now(),
        ]);
        if ($d->opened_at === null) {
            $d->update(['opened_at' => now()]);
            Newsletter::where('id', $d->newsletter_id)->increment('summary_opened');
        }
        if ($isFirstClick) {
            Newsletter::where('id', $d->newsletter_id)->increment('summary_clicked');
        }
    }

    public function recordBounce(string $token, string $type, ?string $reason = null): void
    {
        $d = $this->findByToken($token);
        if (! $d) {
            return;
        }
        if ($type !== 'hard') {
            return;
        }

        if ($d->status !== 'bounced') {
            $d->update(['status' => 'bounced', 'bounce_reason' => $reason]);
            Newsletter::where('id', $d->newsletter_id)->increment('summary_bounced');
            $this->bounces->markBounced($d->email_at_send);
        }
    }

    public function recordComplaint(string $token): void
    {
        $d = $this->findByToken($token);
        if (! $d) {
            return;
        }
        if ($d->status !== 'complained') {
            $d->update(['status' => 'complained']);
            Newsletter::where('id', $d->newsletter_id)->increment('summary_complained');
            $this->bounces->markComplained($d->email_at_send);
        }
    }

    private function findByToken(string $token): ?NewsletterDelivery
    {
        return NewsletterDelivery::where('tracking_token', $token)->first();
    }
}
