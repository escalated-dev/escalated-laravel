<?php

use Escalated\Laravel\Models\Article;
use Escalated\Laravel\Models\ArticleCategory;
use Escalated\Laravel\Models\EscalatedSettings;

/*
 * The widget is public. Its knowledge-base endpoints follow the same two admin
 * settings as the customer knowledge base: off means not found, and a
 * knowledge base that is not public needs a signed-in visitor.
 */

beforeEach(function () {
    EscalatedSettings::set('widget_enabled', '1');

    Article::create([
        'title' => 'Resetting a password',
        'slug' => 'resetting-a-password',
        'body' => 'How to reset your password.',
        'status' => 'published',
        'category_id' => ArticleCategory::create(['name' => 'Accounts', 'slug' => 'accounts'])->id,
    ]);
});

it('tells the widget the knowledge base is off when an admin turns it off', function () {
    EscalatedSettings::set('knowledge_base_enabled', '0');

    $this->getJson(route('escalated.widget.config'))
        ->assertOk()
        ->assertJson(['kb_enabled' => false]);
});

it('does not search articles when the knowledge base is off', function () {
    EscalatedSettings::set('knowledge_base_enabled', '0');

    $this->getJson(route('escalated.widget.articles.search', ['q' => 'password']))->assertNotFound();
});

it('does not show an article when the knowledge base is off', function () {
    EscalatedSettings::set('knowledge_base_enabled', '0');

    $this->getJson(route('escalated.widget.articles.show', 'resetting-a-password'))->assertNotFound();
});

it('keeps a knowledge base that is not public from anonymous widget visitors', function () {
    EscalatedSettings::set('knowledge_base_enabled', '1');
    EscalatedSettings::set('knowledge_base_public', '0');

    $this->getJson(route('escalated.widget.config'))->assertJson(['kb_enabled' => false]);
    $this->getJson(route('escalated.widget.articles.search', ['q' => 'password']))->assertForbidden();
    $this->getJson(route('escalated.widget.articles.show', 'resetting-a-password'))->assertForbidden();
});

it('shows a knowledge base that is not public to a signed-in widget visitor', function () {
    EscalatedSettings::set('knowledge_base_enabled', '1');
    EscalatedSettings::set('knowledge_base_public', '0');

    $this->actingAs($this->createTestUser())
        ->getJson(route('escalated.widget.articles.show', 'resetting-a-password'))
        ->assertOk();
});

it('serves articles when the knowledge base is on and public', function () {
    EscalatedSettings::set('knowledge_base_enabled', '1');
    EscalatedSettings::set('knowledge_base_public', '1');

    $this->getJson(route('escalated.widget.config'))->assertJson(['kb_enabled' => true]);
    $this->getJson(route('escalated.widget.articles.search', ['q' => 'password']))
        ->assertOk()
        ->assertJsonCount(1, 'articles');
});
