<?php

namespace Escalated\Laravel\Services\Newsletter;

use Escalated\Laravel\Models\Contact;
use Escalated\Laravel\Models\Newsletter\Newsletter;
use Escalated\Laravel\Models\Newsletter\NewsletterDelivery;
use Illuminate\Support\Str;

class NewsletterPlannerService
{
    public function __construct(
        private readonly ContactSegmentResolver $segments,
        private readonly BounceSuppressionStore $bounces,
    ) {}

    public function plan(Newsletter $newsletter): void
    {
        $newsletter->update(['status' => 'sending']);

        $contactIds = $this->segments->resolveSendable($newsletter->targetList);
        if (empty($contactIds)) {
            $newsletter->update(['summary_total' => 0]);

            return;
        }

        $contacts = Contact::whereIn('id', $contactIds)->get(['id', 'email']);
        $emails = $contacts->pluck('email')->all();
        $sendableEmails = array_flip(array_map('strtolower', $this->bounces->filterSendable($emails)));

        $rows = [];
        foreach ($contacts as $contact) {
            if (! isset($sendableEmails[strtolower($contact->email)])) {
                continue;
            }
            $rows[] = [
                'newsletter_id' => $newsletter->id,
                'contact_id' => $contact->id,
                'email_at_send' => $contact->email,
                'status' => 'pending',
                'tracking_token' => $this->generateToken(),
                'attempt_count' => 0,
                'is_test' => false,
                'created_at' => now(),
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            NewsletterDelivery::insert($chunk);
        }

        $newsletter->update(['summary_total' => count($rows)]);
    }

    private function generateToken(): string
    {
        return Str::random(40);
    }
}
