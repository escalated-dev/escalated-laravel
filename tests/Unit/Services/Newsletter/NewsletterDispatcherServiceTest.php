<?php

use Escalated\Laravel\Mail\NewsletterMail;
use Escalated\Laravel\Models\Contact;
use Escalated\Laravel\Models\Newsletter\Newsletter;
use Escalated\Laravel\Models\Newsletter\NewsletterDelivery;
use Escalated\Laravel\Models\Newsletter\NewsletterList;
use Escalated\Laravel\Services\Newsletter\NewsletterDispatcherService;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    config(['escalated.enable_newsletters' => true]);
    $this->dispatcher = app(NewsletterDispatcherService::class);
});

function makePendingDelivery(): NewsletterDelivery
{
    $list = NewsletterList::create(['name' => 'L', 'kind' => 'static']);
    $contact = Contact::create(['email' => 'a-'.uniqid().'@example.com']);
    $n = Newsletter::create([
        'subject' => 'Hi', 'from_email' => 'f@x.com', 'target_list_id' => $list->id,
        'body_markdown' => 'hello', 'status' => 'sending', 'summary_total' => 1,
    ]);

    return NewsletterDelivery::create([
        'newsletter_id' => $n->id, 'contact_id' => $contact->id, 'email_at_send' => $contact->email,
        'status' => 'pending', 'tracking_token' => 'tok_'.uniqid(),
    ]);
}

it('claims pending rows, sends mail, and marks them sent', function () {
    $delivery = makePendingDelivery();
    $this->dispatcher->dispatchBatch();
    Mail::assertSent(NewsletterMail::class, 1);
    expect($delivery->fresh()->status)->toBe('sent');
    expect($delivery->fresh()->sent_at)->not->toBeNull();
});

it('respects the batch size config', function () {
    config(['escalated.newsletters.batch_size' => 2]);
    for ($i = 0; $i < 5; $i++) {
        makePendingDelivery();
    }
    $this->dispatcher->dispatchBatch();
    Mail::assertSent(NewsletterMail::class, 2);
});

it('flips the parent newsletter to sent once all deliveries are terminal', function () {
    $delivery = makePendingDelivery();
    $this->dispatcher->dispatchBatch();
    expect($delivery->newsletter->fresh()->status)->toBe('sent');
});

it('does nothing when the feature flag is off', function () {
    config(['escalated.enable_newsletters' => false]);
    $delivery = makePendingDelivery();
    $this->dispatcher->dispatchBatch();
    Mail::assertNothingSent();
    expect($delivery->fresh()->status)->toBe('pending');
});

it('enforces the per-minute rate limit across ticks', function () {
    config(['escalated.newsletters.rate_limit_per_minute' => 2, 'escalated.newsletters.batch_size' => 50]);
    for ($i = 0; $i < 5; $i++) {
        makePendingDelivery();
    }

    // First tick sends up to the rate cap...
    $this->dispatcher->dispatchBatch();
    Mail::assertSent(NewsletterMail::class, 2);

    // ...and a second tick in the same minute sends no more (cap exhausted).
    $this->dispatcher->dispatchBatch();
    Mail::assertSent(NewsletterMail::class, 2);
    expect(NewsletterDelivery::where('status', 'pending')->count())->toBe(3);
});

it('does not claim a delivery whose backoff time is in the future', function () {
    $delivery = makePendingDelivery();
    $delivery->update(['next_attempt_at' => now()->addMinutes(5), 'attempt_count' => 1]);

    $this->dispatcher->dispatchBatch();

    Mail::assertNothingSent();
    expect($delivery->fresh()->status)->toBe('pending');
});

it('auto-pauses a campaign whose first-N deliveries exceed the bounce rate', function () {
    config(['escalated.newsletters.auto_pause_threshold' => 4, 'escalated.newsletters.auto_pause_bounce_rate' => 0.05]);

    $list = NewsletterList::create(['name' => 'L', 'kind' => 'static']);
    $newsletter = Newsletter::create([
        'subject' => 'Hi', 'from_email' => 'f@x.com', 'target_list_id' => $list->id,
        'body_markdown' => 'hello', 'status' => 'sending', 'summary_total' => 6,
    ]);

    $make = function (string $status) use ($newsletter) {
        $contact = Contact::create(['email' => uniqid().'@example.com']);

        return NewsletterDelivery::create([
            'newsletter_id' => $newsletter->id, 'contact_id' => $contact->id,
            'email_at_send' => $contact->email, 'status' => $status, 'tracking_token' => 'tok_'.uniqid(),
        ]);
    };

    // First 4 terminal deliveries: 1 hard bounce = 25% (> 5%).
    $make('sent');
    $make('bounced');
    $make('sent');
    $make('sent');
    // A pending tail so the campaign isn't finalized to "sent" before the check.
    $make('pending');
    $make('pending');

    config(['escalated.newsletters.rate_limit_per_minute' => 1]);
    $this->dispatcher->dispatchBatch();

    expect($newsletter->fresh()->status)->toBe('paused');
});
