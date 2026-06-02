<?php

use Escalated\Laravel\Models\Contact;
use Escalated\Laravel\Models\Newsletter\NewsletterList;
use Escalated\Laravel\Models\Newsletter\NewsletterListMember;
use Escalated\Laravel\Services\Newsletter\ContactSegmentResolver;

beforeEach(function () {
    $this->resolver = app(ContactSegmentResolver::class);
});

it('returns explicit contact ids for static lists', function () {
    $list = NewsletterList::create(['name' => 'Test', 'kind' => 'static']);
    $c1 = Contact::create(['email' => 'a@example.com']);
    $c2 = Contact::create(['email' => 'b@example.com']);
    NewsletterListMember::create(['list_id' => $list->id, 'contact_id' => $c1->id]);
    NewsletterListMember::create(['list_id' => $list->id, 'contact_id' => $c2->id]);

    $ids = $this->resolver->resolve($list);

    expect($ids)->toEqualCanonicalizing([$c1->id, $c2->id]);
});

it('excludes contacts who have opted out of marketing', function () {
    $list = NewsletterList::create(['name' => 'Test', 'kind' => 'static']);
    $c1 = Contact::create(['email' => 'a@example.com']);
    $c2 = Contact::create(['email' => 'b@example.com', 'marketing_opt_out_at' => now()]);
    NewsletterListMember::create(['list_id' => $list->id, 'contact_id' => $c1->id]);
    NewsletterListMember::create(['list_id' => $list->id, 'contact_id' => $c2->id]);

    $ids = $this->resolver->resolveSendable($list);

    expect($ids)->toEqual([$c1->id]);
});

it('evaluates dynamic filter rules', function () {
    Contact::create(['email' => 'low@example.com', 'name' => 'Low']);
    Contact::create(['email' => 'high@example.com', 'name' => 'High']);
    $list = NewsletterList::create([
        'name' => 'High value',
        'kind' => 'dynamic',
        'filter_json' => ['rules' => [['field' => 'name', 'op' => '=', 'value' => 'High']]],
    ]);

    $ids = $this->resolver->resolve($list);

    expect($ids)->toHaveCount(1);
});

it('countMatches returns the dynamic filter cardinality', function () {
    Contact::create(['email' => 'a@example.com', 'name' => 'Alpha']);
    Contact::create(['email' => 'b@example.com', 'name' => 'Alpha']);
    Contact::create(['email' => 'c@example.com', 'name' => 'Beta']);

    $count = $this->resolver->countMatches(['rules' => [['field' => 'name', 'op' => '=', 'value' => 'Alpha']]]);

    expect($count)->toBe(2);
});
