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
