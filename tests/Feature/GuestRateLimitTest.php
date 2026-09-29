<?php

use Escalated\Laravel\Models\EscalatedSettings;
use Escalated\Laravel\Models\Ticket;

beforeEach(function () {
    EscalatedSettings::set('guest_tickets_enabled', '1');
    EscalatedSettings::set('widget_enabled', '1');
    EscalatedSettings::set('chat_enabled', '1');
});

it('limits anonymous submissions before creating a ticket and keeps reads on a separate quota', function (string $path) {
    config(['escalated.guest_rate_limits.submissions_per_minute' => 2]);
    $this->postJson($path, [])->assertUnprocessable();
    $this->postJson($path, [])->assertUnprocessable();
    $this->postJson($path, [
        'name' => 'Guest', 'email' => 'guest@example.com',
        'guest_name' => 'Guest', 'guest_email' => 'guest@example.com',
        'subject' => 'Help', 'description' => 'Details',
    ])->assertTooManyRequests()->assertHeader('Retry-After');
    expect(Ticket::count())->toBe(0);
    $this->getJson('/support/widget/config')->assertOk();
})->with([
    'browser' => '/support/guest',
    'mobile' => '/support/api/v1/mobile/guest/tickets',
    'widget' => '/support/widget/tickets',
    'chat' => '/support/widget/chat/start',
]);

it('shares the submission quota across public transports and ignores claimed email or token changes', function () {
    config(['escalated.guest_rate_limits.submissions_per_minute' => 2]);
    $this->postJson('/support/guest', ['guest_email' => 'one@example.com'])->assertUnprocessable();
    $this->postJson('/support/api/v1/mobile/guest/tickets', ['email' => 'two@example.com'])->assertUnprocessable();
    $this->postJson('/support/widget/tickets', ['email' => 'three@example.com'])->assertTooManyRequests();
    $this->postJson('/support/widget/chat/start', [])->assertTooManyRequests();
    expect(Ticket::count())->toBe(0);
});

it('limits guest reads across token changes and transports', function () {
    config(['escalated.guest_rate_limits.requests_per_minute' => 2]);
    $this->getJson('/support/guest/'.str_repeat('a', 64))->assertNotFound();
    $this->getJson('/support/api/v1/mobile/guest/tickets/'.str_repeat('b', 64))->assertNotFound();
    $this->getJson('/support/guest/'.str_repeat('c', 64))->assertTooManyRequests();
    $this->getJson('/support/widget/config')->assertTooManyRequests();
});

it('limits reply and rating routes even when the capability does not resolve', function (string $path) {
    config(['escalated.guest_rate_limits.replies_per_minute' => 2]);
    $this->postJson($path, ['rating' => 5, 'body' => 'Reply'])->assertNotFound();
    $this->postJson($path, ['rating' => 5, 'body' => 'Reply'])->assertNotFound();
    $this->postJson($path, [])->assertTooManyRequests()->assertHeader('Retry-After');
})->with([
    'browser reply' => '/support/guest/'.str_repeat('a', 64).'/reply',
    'browser rating' => '/support/guest/'.str_repeat('a', 64).'/rate',
    'mobile reply' => '/support/api/v1/mobile/guest/tickets/'.str_repeat('a', 64).'/replies',
    'chat message' => '/support/widget/chat/missing/message',
    'chat rating' => '/support/widget/chat/missing/rate',
]);

it('allows another client IP and restores access after the window expires', function () {
    config(['escalated.guest_rate_limits.submissions_per_minute' => 1]);
    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.1']);
    $this->postJson('/support/guest', [])->assertUnprocessable();
    $this->postJson('/support/guest', [])->assertTooManyRequests();
    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.2']);
    $this->postJson('/support/guest', [])->assertUnprocessable();
    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.1']);
    $this->travel(61)->seconds();
    $this->postJson('/support/guest', [])->assertUnprocessable();
});

it('keeps a positive limit when a host misconfigures zero', function () {
    config(['escalated.guest_rate_limits.submissions_per_minute' => 0]);
    $this->postJson('/support/guest', [])->assertUnprocessable();
    $this->postJson('/support/guest', [])->assertTooManyRequests();
});
