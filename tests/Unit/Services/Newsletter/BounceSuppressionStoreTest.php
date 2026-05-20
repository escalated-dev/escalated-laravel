<?php

use Escalated\Laravel\Services\Newsletter\BounceSuppressionStore;

beforeEach(function () {
    $this->store = app(BounceSuppressionStore::class);
});

it('marks emails as hard-bounced and filters them out', function () {
    $this->store->markBounced('bounced@example.com');
    expect($this->store->isBounced('bounced@example.com'))->toBeTrue();
    expect($this->store->isBounced('ok@example.com'))->toBeFalse();
});

it('filters a list of emails to non-suppressed ones', function () {
    $this->store->markBounced('a@example.com');
    $this->store->markComplained('b@example.com');

    $filtered = $this->store->filterSendable(['a@example.com', 'b@example.com', 'c@example.com']);

    expect($filtered)->toEqual(['c@example.com']);
});

it('is case-insensitive on email comparison', function () {
    $this->store->markBounced('Maria@Example.com');
    expect($this->store->isBounced('maria@example.com'))->toBeTrue();
    expect($this->store->isBounced('MARIA@EXAMPLE.COM'))->toBeTrue();
});

it('deduplicates repeated marks', function () {
    $this->store->markBounced('a@example.com');
    $this->store->markBounced('a@example.com');
    $this->store->markComplained('a@example.com');

    expect($this->store->filterSendable(['a@example.com', 'b@example.com']))->toEqual(['b@example.com']);
});
