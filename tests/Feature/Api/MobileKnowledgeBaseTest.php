<?php

use Escalated\Laravel\Models\Article;
use Escalated\Laravel\Models\ArticleCategory;
use Escalated\Laravel\Models\EscalatedSettings;

it('lists mobile knowledge base articles and categories', function () {
    EscalatedSettings::set('knowledge_base_enabled', 'true');
    EscalatedSettings::set('knowledge_base_public', 'true');

    $category = ArticleCategory::create([
        'name' => 'Billing',
        'slug' => 'billing',
    ]);

    Article::create([
        'category_id' => $category->id,
        'title' => 'How billing works',
        'slug' => 'how-billing-works',
        'body' => '<p>Billing details</p>',
        'status' => 'published',
        'published_at' => now(),
    ]);

    $this->getJson('/support/api/v1/mobile/kb/articles')
        ->assertOk()
        ->assertJsonPath('data.0.slug', 'how-billing-works');

    $this->getJson('/support/api/v1/mobile/kb/categories')
        ->assertOk()
        ->assertJsonPath('data.0.slug', 'billing');
});

it('shows a mobile knowledge base article and records feedback', function () {
    EscalatedSettings::set('knowledge_base_enabled', 'true');
    EscalatedSettings::set('knowledge_base_public', 'true');
    EscalatedSettings::set('knowledge_base_feedback_enabled', 'true');

    $article = Article::create([
        'title' => 'Driver payout schedule',
        'slug' => 'driver-payout-schedule',
        'body' => '<p>Weekly payouts</p>',
        'status' => 'published',
        'published_at' => now(),
    ]);

    $this->getJson('/support/api/v1/mobile/kb/articles/driver-payout-schedule')
        ->assertOk()
        ->assertJsonPath('data.slug', 'driver-payout-schedule');

    $this->postJson('/support/api/v1/mobile/kb/articles/driver-payout-schedule/rate', [
        'helpful' => true,
    ])->assertOk();

    expect($article->fresh()->helpful_count)->toBe(1);
});
