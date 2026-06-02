<?php

use Escalated\Laravel\Models\Contact;
use Escalated\Laravel\Models\Newsletter\Newsletter;
use Escalated\Laravel\Models\Newsletter\NewsletterDelivery;
use Escalated\Laravel\Models\Newsletter\NewsletterList;
use Escalated\Laravel\Services\Newsletter\NewsletterRendererService;

beforeEach(function () {
    $this->renderer = app(NewsletterRendererService::class);
    config(['escalated.newsletters.tracking_enabled' => true]);
    // url() uses the request context — http://localhost is the testbench default
});

function makeDelivery(array $contactAttrs = [], array $newsletterAttrs = []): NewsletterDelivery
{
    $list = NewsletterList::create(['name' => 'Test', 'kind' => 'static']);
    $contact = Contact::create(array_merge(['email' => 'maria@example.com', 'name' => 'Maria Lopez'], $contactAttrs));
    $newsletter = Newsletter::create(array_merge([
        'subject' => 'Hi',
        'from_email' => 'a@example.com',
        'target_list_id' => $list->id,
        'body_markdown' => 'Hello {{ contact.first_name }}!',
        'theme' => 'default',
    ], $newsletterAttrs));

    return NewsletterDelivery::create([
        'newsletter_id' => $newsletter->id,
        'contact_id' => $contact->id,
        'email_at_send' => $contact->email,
        'tracking_token' => 'tok_'.uniqid(),
    ]);
}

it('renders Markdown to HTML', function () {
    $delivery = makeDelivery([], ['body_markdown' => '# Hello']);
    $html = $this->renderer->render($delivery);
    expect($html)->toContain('<h1>Hello</h1>');
});

it('resolves contact merge fields', function () {
    $delivery = makeDelivery(['name' => 'Maria Lopez'], ['body_markdown' => 'Hi {{ contact.first_name }}, your email is {{ contact.email }}.']);
    $html = $this->renderer->render($delivery);
    expect($html)->toContain('Hi Maria, your email is maria@example.com.');
});

it('renders unknown merge fields as empty strings', function () {
    $delivery = makeDelivery([], ['body_markdown' => 'Foo {{ contact.does_not_exist }} bar']);
    $html = $this->renderer->render($delivery);
    expect($html)->toContain('Foo  bar');
    expect($html)->not->toContain('{{');
});

it('rewrites href attributes to the click-tracking endpoint', function () {
    $delivery = makeDelivery([], ['body_markdown' => '[Click here](https://landing.example/page)']);
    $html = $this->renderer->render($delivery);
    expect($html)->toMatch('#href="http://localhost/escalated/n/c/[^"]+"#');
    expect($html)->not->toContain('href="https://landing.example/page"');
});

it('appends the tracking pixel before the body close', function () {
    $delivery = makeDelivery();
    $html = $this->renderer->render($delivery);
    expect($html)->toMatch('#<img src="http://localhost/escalated/n/o/[^"]+\.gif"#');
});

it('skips click rewriting and pixel when tracking_enabled is false', function () {
    config(['escalated.newsletters.tracking_enabled' => false]);
    $delivery = makeDelivery([], ['body_markdown' => '[Click here](https://landing.example/page)']);
    $html = $this->renderer->render($delivery);
    expect($html)->toContain('href="https://landing.example/page"');
    expect($html)->not->toContain('/escalated/n/o/');
});

it('rejects javascript: URLs in click-rewriting', function () {
    $delivery = makeDelivery([], ['body_markdown' => '[Bad](javascript:alert(1))']);
    $html = $this->renderer->render($delivery);
    expect($html)->not->toContain('javascript:');
});

it('does NOT rewrite the unsubscribe or view-in-browser links', function () {
    $delivery = makeDelivery();
    $html = $this->renderer->render($delivery);
    expect($html)->toMatch('#href="http://localhost/escalated/n/u/[^"]+"#');
});
