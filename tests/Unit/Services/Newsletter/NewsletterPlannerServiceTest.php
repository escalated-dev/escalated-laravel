<?php

use Escalated\Laravel\Models\Contact;
use Escalated\Laravel\Models\Newsletter\Newsletter;
use Escalated\Laravel\Models\Newsletter\NewsletterDelivery;
use Escalated\Laravel\Models\Newsletter\NewsletterList;
use Escalated\Laravel\Models\Newsletter\NewsletterListMember;
use Escalated\Laravel\Services\Newsletter\BounceSuppressionStore;
use Escalated\Laravel\Services\Newsletter\NewsletterPlannerService;

beforeEach(function () {
    $this->planner = app(NewsletterPlannerService::class);
});

it('creates one pending delivery per sendable contact', function () {
    $list = NewsletterList::create(['name' => 'L', 'kind' => 'static']);
    $c1 = Contact::create(['email' => 'a@example.com']);
    $c2 = Contact::create(['email' => 'b@example.com']);
    NewsletterListMember::create(['list_id' => $list->id, 'contact_id' => $c1->id]);
    NewsletterListMember::create(['list_id' => $list->id, 'contact_id' => $c2->id]);
    $n = Newsletter::create(['subject' => 'X', 'from_email' => 'f@x.com', 'target_list_id' => $list->id, 'status' => 'scheduled', 'body_markdown' => 'hi']);

    $this->planner->plan($n);

    expect(NewsletterDelivery::where('newsletter_id', $n->id)->count())->toBe(2);
    expect($n->fresh()->status)->toBe('sending');
    expect($n->fresh()->summary_total)->toBe(2);
});

it('skips opted-out contacts', function () {
    $list = NewsletterList::create(['name' => 'L', 'kind' => 'static']);
    $c1 = Contact::create(['email' => 'a@example.com']);
    $c2 = Contact::create(['email' => 'b@example.com', 'marketing_opt_out_at' => now()]);
    NewsletterListMember::create(['list_id' => $list->id, 'contact_id' => $c1->id]);
    NewsletterListMember::create(['list_id' => $list->id, 'contact_id' => $c2->id]);
    $n = Newsletter::create(['subject' => 'X', 'from_email' => 'f@x.com', 'target_list_id' => $list->id, 'status' => 'scheduled', 'body_markdown' => 'hi']);

    $this->planner->plan($n);

    expect(NewsletterDelivery::where('newsletter_id', $n->id)->count())->toBe(1);
});

it('skips contacts whose email has hard-bounced', function () {
    app(BounceSuppressionStore::class)->markBounced('a@example.com');
    $list = NewsletterList::create(['name' => 'L', 'kind' => 'static']);
    $c1 = Contact::create(['email' => 'a@example.com']);
    $c2 = Contact::create(['email' => 'c@example.com']);
    NewsletterListMember::create(['list_id' => $list->id, 'contact_id' => $c1->id]);
    NewsletterListMember::create(['list_id' => $list->id, 'contact_id' => $c2->id]);
    $n = Newsletter::create(['subject' => 'X', 'from_email' => 'f@x.com', 'target_list_id' => $list->id, 'status' => 'scheduled', 'body_markdown' => 'hi']);

    $this->planner->plan($n);

    expect(NewsletterDelivery::where('newsletter_id', $n->id)->where('email_at_send', 'a@example.com')->count())->toBe(0);
    expect(NewsletterDelivery::where('newsletter_id', $n->id)->where('email_at_send', 'c@example.com')->count())->toBe(1);
});

it('snapshots the email at plan time', function () {
    $list = NewsletterList::create(['name' => 'L', 'kind' => 'static']);
    $c1 = Contact::create(['email' => 'a@example.com']);
    NewsletterListMember::create(['list_id' => $list->id, 'contact_id' => $c1->id]);
    $n = Newsletter::create(['subject' => 'X', 'from_email' => 'f@x.com', 'target_list_id' => $list->id, 'status' => 'scheduled', 'body_markdown' => 'hi']);

    $this->planner->plan($n);
    $c1->update(['email' => 'changed@example.com']);

    $d = NewsletterDelivery::where('newsletter_id', $n->id)->first();
    expect($d->email_at_send)->toBe('a@example.com');
});

it('generates unique tracking tokens', function () {
    $list = NewsletterList::create(['name' => 'L', 'kind' => 'static']);
    for ($i = 0; $i < 5; $i++) {
        $c = Contact::create(['email' => "c{$i}@example.com"]);
        NewsletterListMember::create(['list_id' => $list->id, 'contact_id' => $c->id]);
    }
    $n = Newsletter::create(['subject' => 'X', 'from_email' => 'f@x.com', 'target_list_id' => $list->id, 'status' => 'scheduled', 'body_markdown' => 'hi']);

    $this->planner->plan($n);

    $tokens = NewsletterDelivery::where('newsletter_id', $n->id)->pluck('tracking_token')->all();
    expect($tokens)->toHaveCount(5);
    expect(array_unique($tokens))->toHaveCount(5);
});
