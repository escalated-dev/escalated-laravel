<?php

use Escalated\Laravel\Enums\ChatSessionStatus;
use Escalated\Laravel\Models\ChatSession;
use Escalated\Laravel\Models\EscalatedSettings;
use Escalated\Laravel\Models\Ticket;
use Illuminate\Support\Facades\Route;

/*
 * The guest, widget and live-chat routes answer only while their feature is
 * on. They are switched by runtime settings (guest_tickets_enabled,
 * widget_enabled, chat_enabled), so the routes stay registered and answer 404
 * when the feature is off. The cloud webhook receiver depends on config
 * (escalated.hosted.signing_secret) and is registered only when it is set.
 */

function guestPaths(): array
{
    $token = str_repeat('a', 64);

    return [
        ['GET', '/support/guest/create'],
        ['POST', '/support/guest'],
        ['GET', '/support/guest/'.$token],
        ['POST', '/support/guest/'.$token.'/reply'],
        ['POST', '/support/guest/'.$token.'/rate'],
        ['POST', '/support/guest/verification'],
        ['POST', '/support/guest/lookup'],
    ];
}

it('answers 404 on every guest route while guest tickets are disabled', function () {
    EscalatedSettings::set('guest_tickets_enabled', '0');

    foreach (guestPaths() as [$method, $path]) {
        $this->json($method, $path, ['email' => 'guest@example.com', 'purpose' => 'ticket'])
            ->assertNotFound();
    }
});

it('keeps the guest routes on by default', function () {
    $this->get('/support/guest/create')->assertOk();
});

it('shows an existing guest ticket only while guest tickets are enabled', function () {
    $ticket = Ticket::factory()->create(['guest_email' => 'guest@example.com', 'guest_name' => 'Guest']);
    $token = $this->guestToken($ticket);

    $this->get('/support/guest/'.$token)->assertOk();

    EscalatedSettings::set('guest_tickets_enabled', '0');

    $this->get('/support/guest/'.$token)->assertNotFound();
});

it('answers 404 on the widget routes while the widget is disabled', function () {
    EscalatedSettings::set('widget_enabled', '0');

    $this->getJson('/support/widget/config')->assertNotFound();
    $this->getJson('/support/widget/articles?q=x')->assertNotFound();
    $this->getJson('/support/widget/articles/any-slug')->assertNotFound();
    $this->postJson('/support/widget/tickets', [])->assertNotFound();
    $this->getJson('/support/widget/tickets/ESC-00001')->assertNotFound();
});

it('serves the widget routes once the widget is enabled', function () {
    EscalatedSettings::set('widget_enabled', '1');

    $this->getJson('/support/widget/config')->assertOk()->assertJson(['enabled' => true]);
});

it('answers 404 on the visitor chat routes while chat is disabled', function () {
    EscalatedSettings::set('chat_enabled', '0');
    $session = ChatSession::factory()->create(['status' => ChatSessionStatus::Active]);
    $path = '/support/widget/chat/'.$session->customer_session_id;

    $this->postJson('/support/widget/chat/start', [])->assertNotFound();
    $this->postJson($path.'/message', ['body' => 'Hi'])->assertNotFound();
    $this->postJson($path.'/messages', ['body' => 'Hi'])->assertNotFound();
    $this->getJson($path.'/messages')->assertNotFound();
    $this->postJson($path.'/typing')->assertNotFound();
    $this->postJson($path.'/end')->assertNotFound();
    $this->postJson($path.'/rate', ['rating' => 5])->assertNotFound();
    $this->postJson('/support/widget/verification', ['email' => 'a@example.com', 'purpose' => 'chat'])->assertNotFound();
});

it('still tells the widget chat is off', function () {
    EscalatedSettings::set('chat_enabled', '0');

    $this->getJson('/support/widget/chat/availability')
        ->assertOk()
        ->assertJson(['available' => false, 'message' => 'Chat is disabled.']);
});

it('answers 404 on widget ticket verification and lookup while guest tickets are disabled', function () {
    EscalatedSettings::set('guest_tickets_enabled', '0');

    $this->postJson('/support/widget/verification', ['email' => 'a@example.com', 'purpose' => 'ticket'])->assertNotFound();
    $this->postJson('/support/widget/lookup', [])->assertNotFound();
});

it('does not register the cloud webhook receiver without a signing secret', function () {
    expect(config('escalated.hosted.signing_secret'))->toBeNull()
        ->and(Route::has('escalated.cloud.webhook'))->toBeFalse();

    $this->postJson('/escalated/cloud/webhook', [])->assertNotFound();
});

it('registers the cloud webhook receiver when a signing secret is configured', function () {
    config(['escalated.hosted.signing_secret' => 'whsec_site']);
    Route::group([], __DIR__.'/../../routes/cloud.php');
    Route::getRoutes()->refreshNameLookups();

    expect(Route::has('escalated.cloud.webhook'))->toBeTrue();
    $this->postJson('/escalated/cloud/webhook', [])->assertStatus(401);
});
