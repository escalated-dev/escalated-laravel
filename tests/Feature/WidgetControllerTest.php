<?php

use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Models\Article;
use Escalated\Laravel\Models\ArticleCategory;
use Escalated\Laravel\Models\Contact;
use Escalated\Laravel\Models\EscalatedSettings;
use Escalated\Laravel\Models\Ticket;

it('config endpoint returns widget settings when enabled', function () {
    EscalatedSettings::set('widget_enabled', '1');
    EscalatedSettings::set('widget_color', '#123456');
    EscalatedSettings::set('widget_greeting', 'Hello!');

    $response = $this->getJson(route('escalated.widget.config'));

    $response->assertOk();
    $response->assertJson([
        'enabled' => true,
        'color' => '#123456',
        'greeting' => 'Hello!',
    ]);
});

it('returns 403 when widget is disabled', function () {
    EscalatedSettings::set('widget_enabled', '0');

    $response = $this->getJson(route('escalated.widget.config'));
    $response->assertStatus(403);
});

it('article search returns published articles only', function () {
    EscalatedSettings::set('widget_enabled', '1');

    $category = ArticleCategory::create([
        'name' => 'General',
        'slug' => 'general',
    ]);

    Article::create([
        'title' => 'Published Guide',
        'slug' => 'published-guide',
        'body' => 'This is a published article about testing.',
        'status' => 'published',
        'category_id' => $category->id,
    ]);

    Article::create([
        'title' => 'Draft Guide',
        'slug' => 'draft-guide',
        'body' => 'This is a draft article about testing.',
        'status' => 'draft',
        'category_id' => $category->id,
    ]);

    $response = $this->getJson(route('escalated.widget.articles.search', ['q' => 'testing']));

    $response->assertOk();
    $articles = $response->json('articles');
    expect($articles)->toHaveCount(1);
    expect($articles[0]['title'])->toBe('Published Guide');
});

it('article detail returns content', function () {
    EscalatedSettings::set('widget_enabled', '1');

    $category = ArticleCategory::create([
        'name' => 'Help',
        'slug' => 'help',
    ]);

    Article::create([
        'title' => 'My Article',
        'slug' => 'my-article',
        'body' => '<p>Article content here.</p>',
        'status' => 'published',
        'category_id' => $category->id,
    ]);

    $response = $this->getJson(route('escalated.widget.articles.show', 'my-article'));

    $response->assertOk();
    $response->assertJson([
        'title' => 'My Article',
        'slug' => 'my-article',
        'body' => '<p>Article content here.</p>',
    ]);
});

it('ticket creation works with valid data', function () {
    EscalatedSettings::set('widget_enabled', '1');
    EscalatedSettings::set('guest_tickets_enabled', '1');

    $response = $this->postJson(route('escalated.widget.tickets.store'), [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'subject' => 'Help needed',
        'description' => 'I need assistance with my account.',
    ]);

    $response->assertCreated();
    $response->assertJsonStructure(['message', 'reference']);

    $this->assertDatabaseHas('escalated_tickets', [
        'guest_name' => 'Jane Doe',
        'guest_email' => 'jane@example.com',
        'subject' => 'Help needed',
    ]);
});

it('widget ticket creation resolves/creates a Contact and links the ticket', function () {
    EscalatedSettings::set('widget_enabled', '1');
    EscalatedSettings::set('guest_tickets_enabled', '1');

    $this->postJson(route('escalated.widget.tickets.store'), [
        'name' => 'Alice',
        'email' => 'alice@example.com',
        'subject' => 'First ticket',
        'description' => 'body',
    ]);

    $this->assertDatabaseHas('escalated_contacts', [
        'email' => 'alice@example.com',
        'name' => 'Alice',
    ]);

    $contact = Contact::where('email', 'alice@example.com')->first();
    expect($contact)->not->toBeNull();

    $this->assertDatabaseHas('escalated_tickets', [
        'guest_email' => 'alice@example.com',
        'contact_id' => $contact->id,
    ]);
});

it('dedupes repeat widget submissions onto the same Contact', function () {
    EscalatedSettings::set('widget_enabled', '1');
    EscalatedSettings::set('guest_tickets_enabled', '1');

    // First submission — creates the Contact
    $this->postJson(route('escalated.widget.tickets.store'), [
        'name' => 'Alice',
        'email' => 'alice@example.com',
        'subject' => 'First',
        'description' => 'body',
    ]);

    // Second submission from the same email (different casing, to exercise
    // normalization) — should reuse the Contact
    $this->postJson(route('escalated.widget.tickets.store'), [
        'name' => 'Alice',
        'email' => 'ALICE@example.com',
        'subject' => 'Second',
        'description' => 'body',
    ]);

    $contacts = Contact::where('email', 'alice@example.com')->get();
    expect($contacts)->toHaveCount(1);

    $tickets = Ticket::where('contact_id', $contacts->first()->id)->get();
    expect($tickets)->toHaveCount(2);
});

it('ticket lookup by reference and email works', function () {
    EscalatedSettings::set('widget_enabled', '1');

    $ticket = Ticket::factory()->create([
        'reference' => 'WDG-00001',
        'guest_email' => 'guest@example.com',
        'status' => TicketStatus::Open,
    ]);

    $response = $this->getJson(
        route('escalated.widget.tickets.status', $ticket->reference).'?email=guest@example.com'
    );

    $response->assertOk();
    $response->assertJson([
        'reference' => 'WDG-00001',
        'status' => 'open',
    ]);
});

it('ticket lookup returns 404 for wrong email', function () {
    EscalatedSettings::set('widget_enabled', '1');

    $ticket = Ticket::factory()->create([
        'reference' => 'WDG-00002',
        'guest_email' => 'guest@example.com',
    ]);

    $response = $this->getJson(
        route('escalated.widget.tickets.status', $ticket->reference).'?email=wrong@example.com'
    );

    $response->assertNotFound();
});

it('rate limiting is applied to widget routes', function () {
    EscalatedSettings::set('widget_enabled', '1');

    // The widget routes use throttle:60,1 middleware
    // Verify throttle middleware is registered by making many requests
    // We just verify the route works and the ThrottleRequests middleware is active
    $response = $this->getJson(route('escalated.widget.config'));
    $response->assertOk();

    // Check that rate limit headers are present
    expect($response->headers->has('X-RateLimit-Limit'))->toBeTrue();
});

it('returns 403 for all endpoints when widget disabled', function () {
    EscalatedSettings::set('widget_enabled', '0');

    $this->getJson(route('escalated.widget.config'))->assertStatus(403);
    $this->getJson(route('escalated.widget.articles.search', ['q' => 'test']))->assertStatus(403);
    $this->getJson(route('escalated.widget.articles.show', 'any-slug'))->assertStatus(403);
    $this->postJson(route('escalated.widget.tickets.store'), [
        'name' => 'Test',
        'email' => 'test@test.com',
        'subject' => 'Test',
        'description' => 'Test',
    ])->assertStatus(403);
});

// --- Guest policy integration with widget ticket creation ---

it('respects guest_policy unassigned mode (default) writing guest_* fields', function () {
    EscalatedSettings::set('widget_enabled', '1');
    EscalatedSettings::set('guest_tickets_enabled', '1');
    EscalatedSettings::set('guest_policy_mode', 'unassigned');

    $response = $this->postJson(route('escalated.widget.tickets.store'), [
        'name' => 'Alice',
        'email' => 'alice@example.com',
        'subject' => 'Hello',
        'description' => 'Question.',
    ]);

    $response->assertCreated();
    $this->assertDatabaseHas('escalated_tickets', [
        'guest_email' => 'alice@example.com',
        'requester_id' => null,
        'requester_type' => null,
    ]);
});

it('respects guest_policy guest_user mode routing to the configured host user', function () {
    EscalatedSettings::set('widget_enabled', '1');
    EscalatedSettings::set('guest_tickets_enabled', '1');
    EscalatedSettings::set('guest_policy_mode', 'guest_user');
    EscalatedSettings::set('guest_policy_user_id', '42');

    $response = $this->postJson(route('escalated.widget.tickets.store'), [
        'name' => 'Bob',
        'email' => 'bob@example.com',
        'subject' => 'Hi',
        'description' => 'Another question.',
    ]);

    $response->assertCreated();
    $this->assertDatabaseHas('escalated_tickets', [
        'requester_id' => 42,
        'requester_type' => config('escalated.user_model', 'App\\Models\\User'),
        'guest_email' => 'bob@example.com',
    ]);
});

it('falls through to unassigned behavior when guest_user mode has no user id', function () {
    EscalatedSettings::set('widget_enabled', '1');
    EscalatedSettings::set('guest_tickets_enabled', '1');
    EscalatedSettings::set('guest_policy_mode', 'guest_user');
    // user_id intentionally missing / zero
    EscalatedSettings::set('guest_policy_user_id', '');

    $response = $this->postJson(route('escalated.widget.tickets.store'), [
        'name' => 'Charlie',
        'email' => 'charlie@example.com',
        'subject' => 'Help',
        'description' => 'Guest user fallback scenario.',
    ]);

    $response->assertCreated();
    $this->assertDatabaseHas('escalated_tickets', [
        'guest_email' => 'charlie@example.com',
        'requester_id' => null,
        'requester_type' => null,
    ]);
});

it('prompt_signup mode uses unassigned ticket-creation path (signup invite is separate)', function () {
    EscalatedSettings::set('widget_enabled', '1');
    EscalatedSettings::set('guest_tickets_enabled', '1');
    EscalatedSettings::set('guest_policy_mode', 'prompt_signup');

    $response = $this->postJson(route('escalated.widget.tickets.store'), [
        'name' => 'Dana',
        'email' => 'dana@example.com',
        'subject' => 'Hi',
        'description' => 'Signup-prompt scenario.',
    ]);

    $response->assertCreated();
    $this->assertDatabaseHas('escalated_tickets', [
        'guest_email' => 'dana@example.com',
        'requester_id' => null,
    ]);
});
