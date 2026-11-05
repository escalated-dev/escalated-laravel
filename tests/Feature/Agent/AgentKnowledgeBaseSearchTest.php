<?php

/*
 * Agents search the knowledge base from the ticket workspace (Related panel,
 * composer "insert article link"): ADR 2026-11-05-agent-panel-data, section 5.
 */

use Escalated\Laravel\Models\Article;
use Escalated\Laravel\Models\ArticleCategory;
use Escalated\Laravel\Models\EscalatedSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);
});

function kbArticle(array $attributes = []): Article
{
    static $n = 0;
    $n++;

    return Article::create(array_merge([
        'title' => "Article {$n}",
        'slug' => "article-{$n}",
        'body' => '<p>Body</p>',
        'status' => 'published',
        'published_at' => now(),
    ], $attributes));
}

it('returns matching published articles with the contract fields', function () {
    $category = ArticleCategory::create(['name' => 'Billing', 'slug' => 'billing']);
    $article = kbArticle([
        'title' => 'Resetting your password',
        'slug' => 'resetting-your-password',
        'body' => '<p>Open <strong>Settings</strong> and choose Reset.</p>',
        'category_id' => $category->id,
    ]);

    $this->actingAs($this->createAgent())
        ->getJson(route('escalated.agent.kb.search', ['q' => 'password']))
        ->assertOk()
        ->assertExactJson(['data' => [[
            'id' => $article->id,
            'title' => 'Resetting your password',
            'excerpt' => 'Open Settings and choose Reset.',
            'url' => route('escalated.customer.kb.show', 'resetting-your-password'),
            'category' => ['id' => $category->id, 'name' => 'Billing'],
            'updated_at' => $article->updated_at->toIso8601String(),
        ]]]);
});

it('never returns drafts', function () {
    kbArticle(['title' => 'Draft about refunds', 'status' => 'draft']);
    kbArticle(['title' => 'Refunds explained']);

    $titles = $this->actingAs($this->createAgent())
        ->getJson(route('escalated.agent.kb.search', ['q' => 'refunds']))
        ->assertOk()
        ->json('data.*.title');

    expect($titles)->toBe(['Refunds explained']);
});

it('ranks title matches before body matches, then the most recently updated', function () {
    Carbon::setTestNow('2026-11-01 10:00:00');
    $bodyOnly = kbArticle(['title' => 'Getting started', 'body' => 'How invoices work']);
    $olderTitle = kbArticle(['title' => 'Invoices (old)']);
    Carbon::setTestNow('2026-11-02 10:00:00');
    $newerTitle = kbArticle(['title' => 'Invoices (new)']);
    Carbon::setTestNow();

    $ids = $this->actingAs($this->createAgent())
        ->getJson(route('escalated.agent.kb.search', ['q' => 'invoices']))
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toBe([$newerTitle->id, $olderTitle->id, $bodyOnly->id]);
});

it('ignores case on every database', function () {
    $article = kbArticle(['title' => 'Resetting your Password']);

    $this->actingAs($this->createAgent())
        ->getJson(route('escalated.agent.kb.search', ['q' => 'PASSWORD']))
        ->assertOk()
        ->assertJsonPath('data.0.id', $article->id);
});

it('treats LIKE wildcards in the query as literal text', function (string $q) {
    kbArticle(['title' => 'Plain title', 'body' => 'Nothing special']);
    $literal = kbArticle(['title' => 'Codes: 100%% off, a_b and x__y', 'body' => '']);

    $ids = $this->actingAs($this->createAgent())
        ->getJson(route('escalated.agent.kb.search', ['q' => $q]))
        ->assertOk()
        ->json('data.*.id');

    expect($ids)->toBe([$literal->id]);
})->with(['%%', '__', '0%', 'a_b']);

it('caps the number of results', function () {
    foreach (range(1, 25) as $i) {
        kbArticle(['title' => "Shipping guide {$i}"]);
    }
    $agent = $this->createAgent();

    $this->actingAs($agent)->getJson(route('escalated.agent.kb.search', ['q' => 'shipping']))
        ->assertOk()->assertJsonCount(10, 'data');
    $this->actingAs($agent)->getJson(route('escalated.agent.kb.search', ['q' => 'shipping', 'limit' => 20]))
        ->assertOk()->assertJsonCount(20, 'data');
    $this->actingAs($agent)->getJson(route('escalated.agent.kb.search', ['q' => 'shipping', 'limit' => 21]))
        ->assertUnprocessable();
});

it('validates the query', function (string $q) {
    $this->actingAs($this->createAgent())
        ->getJson(route('escalated.agent.kb.search', ['q' => $q]))
        ->assertUnprocessable();
})->with(['', 'a', 'too long' => str_repeat('a', 201)]);

it('answers 404 while the knowledge base is off', function () {
    EscalatedSettings::set('knowledge_base_enabled', '0');
    kbArticle(['title' => 'Password help']);

    $this->actingAs($this->createAgent())
        ->getJson(route('escalated.agent.kb.search', ['q' => 'password']))
        ->assertNotFound();
});

it('is for agents only', function () {
    kbArticle(['title' => 'Password help']);

    $this->actingAs($this->createTestUser())
        ->getJson(route('escalated.agent.kb.search', ['q' => 'password']))
        ->assertForbidden();
});

it('also escapes the customer knowledge base search', function () {
    kbArticle(['title' => 'Plain title']);

    $this->actingAs($this->createTestUser())
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => ''])
        ->get(route('escalated.customer.kb.index', ['search' => '%']))
        ->assertOk()
        ->assertJsonCount(0, 'props.articles.data');
});
