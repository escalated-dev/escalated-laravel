<?php

use Escalated\Laravel\Events\ChatStarted;
use Escalated\Laravel\Events\ReplyCreated;
use Escalated\Laravel\Models\EscalatedSettings;
use Escalated\Laravel\Models\Ticket;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia;

/**
 * What the shared frontend reads from this host: the widget's chat transcript
 * and availability message, and the keys the Admin Settings page may show.
 */
it('keeps the visitor\'s opening chat message in the transcript', function () {
    Event::fake([ChatStarted::class, ReplyCreated::class]);
    EscalatedSettings::set('chat_enabled', '1');

    $start = $this->postJson('/support/widget/chat/start', [
        ...$this->guestProof('visitor@example.com', 'chat'),
        'name' => 'Visitor',
        'email' => 'visitor@example.com',
        'message' => 'Hello, my order never arrived',
    ])->assertCreated();

    $messages = $this->getJson('/support/widget/chat/'.$start->json('session_id').'/messages')
        ->assertOk()
        ->json('messages');

    expect($messages)->toHaveCount(1)
        ->and($messages[0]['body'])->toBe('Hello, my order never arrived')
        ->and($messages[0]['is_agent'])->toBeFalse()
        ->and(Ticket::latest('id')->first()->description)->toBe('Hello, my order never arrived');
    Event::assertNotDispatched(ReplyCreated::class);
});

it('starts a chat without a message and without an empty reply', function () {
    Event::fake([ChatStarted::class]);
    EscalatedSettings::set('chat_enabled', '1');

    $start = $this->postJson('/support/widget/chat/start', [
        ...$this->guestProof('quiet@example.com', 'chat'),
        'name' => 'Quiet',
        'email' => 'quiet@example.com',
    ])->assertCreated();

    expect($this->getJson('/support/widget/chat/'.$start->json('session_id').'/messages')->json('messages'))->toBe([]);
});

it('sends the availability message under the key the widget reads', function () {
    EscalatedSettings::set('chat_enabled', '0');

    $this->getJson('/support/widget/chat/availability')
        ->assertOk()
        ->assertJson(['available' => false, 'message' => 'Chat is disabled.']);
});

it('lists exactly the settings it saves, so the page hides the rest', function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);
    $this->actingAs($this->createAdmin());

    $this->get(route('escalated.admin.settings'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Escalated/Admin/Settings', false)
            ->where('supported_settings', function ($keys) {
                $keys = collect($keys);

                return $keys->contains('guest_tickets_enabled')
                    && $keys->contains('widget_allowed_origins')
                    && ! $keys->contains('chat_enabled')
                    && ! $keys->contains('widget_primary_color')
                    && ! $keys->contains('widget_custom_css');
            }));
});
