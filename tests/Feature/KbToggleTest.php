<?php

use Escalated\Laravel\Models\Article;
use Escalated\Laravel\Models\ArticleCategory;
use Escalated\Laravel\Models\EscalatedSettings;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    Gate::define('escalated-agent', fn ($user) => $user->is_agent || $user->is_admin);
    Gate::define('escalated-admin', fn ($user) => $user->is_admin);
});

it('KB controller returns 404 when KB is disabled', function () {
    EscalatedSettings::set('knowledge_base_enabled', '0');

    $user = $this->createTestUser();

    $response = $this->actingAs($user)
        ->get(route('escalated.customer.kb.index'));

    $response->assertNotFound();
});

it('KB controller returns 403 when not public and unauthenticated', function () {
    EscalatedSettings::set('knowledge_base_enabled', '1');
    EscalatedSettings::set('knowledge_base_public', '0');

    // Use withoutMiddleware to bypass auth middleware, simulating unauthenticated access
    $response = $this->withoutMiddleware(Authenticate::class)
        ->get(route('escalated.customer.kb.index'));

    $response->assertForbidden();
});

it('KB article show returns 404 when KB disabled', function () {
    EscalatedSettings::set('knowledge_base_enabled', '0');

    $user = $this->createTestUser();

    $response = $this->actingAs($user)
        ->get(route('escalated.customer.kb.show', 'any-slug'));

    $response->assertNotFound();
});

it('feedback endpoint is blocked when feedback disabled', function () {
    EscalatedSettings::set('knowledge_base_enabled', '1');
    EscalatedSettings::set('knowledge_base_feedback_enabled', '0');

    $user = $this->createTestUser();

    $category = ArticleCategory::create([
        'name' => 'General',
        'slug' => 'general',
    ]);

    Article::create([
        'title' => 'Test Article',
        'slug' => 'test-article',
        'body' => 'Content here.',
        'status' => 'published',
        'category_id' => $category->id,
    ]);

    $response = $this->actingAs($user)
        ->post(route('escalated.customer.kb.feedback', 'test-article'), [
            'helpful' => true,
        ]);

    $response->assertNotFound();
});

it('KB settings are properly stored and retrieved', function () {
    EscalatedSettings::set('knowledge_base_enabled', '1');
    EscalatedSettings::set('knowledge_base_public', '0');
    EscalatedSettings::set('knowledge_base_feedback_enabled', '1');

    expect(EscalatedSettings::knowledgeBaseEnabled())->toBeTrue();
    expect(EscalatedSettings::knowledgeBasePublic())->toBeFalse();
    expect(EscalatedSettings::knowledgeBaseFeedbackEnabled())->toBeTrue();
});

it('KB settings default to true when not set', function () {
    expect(EscalatedSettings::knowledgeBaseEnabled())->toBeTrue();
    expect(EscalatedSettings::knowledgeBasePublic())->toBeTrue();
    expect(EscalatedSettings::knowledgeBaseFeedbackEnabled())->toBeTrue();
});
