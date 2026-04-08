<?php

use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Models\Article;
use Escalated\Laravel\Models\ArticleCategory;
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
