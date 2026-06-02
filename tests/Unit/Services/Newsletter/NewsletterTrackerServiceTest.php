<?php

use Escalated\Laravel\Models\Contact;
use Escalated\Laravel\Models\Newsletter\Newsletter;
use Escalated\Laravel\Models\Newsletter\NewsletterDelivery;
use Escalated\Laravel\Models\Newsletter\NewsletterList;
use Escalated\Laravel\Services\Newsletter\BounceSuppressionStore;
use Escalated\Laravel\Services\Newsletter\NewsletterTrackerService;

beforeEach(function () {
    $this->tracker = app(NewsletterTrackerService::class);
});

function sentDelivery(): NewsletterDelivery
{
    $list = NewsletterList::create(['name' => 'L', 'kind' => 'static']);
    $contact = Contact::create(['email' => 'a-'.uniqid().'@example.com']);
    $n = Newsletter::create([
        'subject' => 'Hi', 'from_email' => 'f@x.com', 'target_list_id' => $list->id,
        'body_markdown' => 'hi', 'status' => 'sending',
    ]);

    return NewsletterDelivery::create([
        'newsletter_id' => $n->id, 'contact_id' => $contact->id, 'email_at_send' => $contact->email,
        'status' => 'sent', 'tracking_token' => 'tok-'.uniqid(), 'sent_at' => now(),
    ]);
}

it('records an open and updates summary, first-event-wins', function () {
    $d = sentDelivery();
    $this->tracker->recordOpen($d->tracking_token);
    $first = $d->fresh()->opened_at;
    expect($first)->not->toBeNull();
    $this->tracker->recordOpen($d->tracking_token);
    expect($d->fresh()->opened_at->equalTo($first))->toBeTrue();
    expect($d->newsletter->fresh()->summary_opened)->toBe(1);
});

it('records a click, increments clicks_count, sets last_clicked_at', function () {
    $d = sentDelivery();
    $this->tracker->recordClick($d->tracking_token, 'https://example.com/a');
    $this->tracker->recordClick($d->tracking_token, 'https://example.com/b');
    $fresh = $d->fresh();
    expect($fresh->clicks_count)->toBe(2);
    expect($fresh->last_clicked_at)->not->toBeNull();
    expect($d->newsletter->fresh()->summary_clicked)->toBe(1);
});

it('records a hard bounce, marks delivery, and updates the suppression store', function () {
    $d = sentDelivery();
    $email = $d->email_at_send;
    $this->tracker->recordBounce($d->tracking_token, 'hard', '550 mailbox not found');
    expect($d->fresh()->status)->toBe('bounced');
    expect($d->fresh()->bounce_reason)->toBe('550 mailbox not found');
    expect(app(BounceSuppressionStore::class)->isBounced($email))->toBeTrue();
});

it('records a complaint and suppresses the email', function () {
    $d = sentDelivery();
    $email = $d->email_at_send;
    $this->tracker->recordComplaint($d->tracking_token);
    expect($d->fresh()->status)->toBe('complained');
    expect(app(BounceSuppressionStore::class)->isBounced($email))->toBeTrue();
});

it('returns silently on unknown tokens (no exception)', function () {
    expect(fn () => $this->tracker->recordOpen('nope'))->not->toThrow(Throwable::class);
});

it('ignores opens after a bounce', function () {
    $d = sentDelivery();
    $this->tracker->recordBounce($d->tracking_token, 'hard', '550');
    $this->tracker->recordOpen($d->tracking_token);
    expect($d->fresh()->opened_at)->toBeNull();
});
