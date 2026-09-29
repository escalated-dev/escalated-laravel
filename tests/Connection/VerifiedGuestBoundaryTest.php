<?php

use Escalated\Laravel\Contracts\TenantResolver;
use Escalated\Laravel\Events\TicketCreated;
use Escalated\Laravel\Models\EscalatedSettings;
use Escalated\Laravel\Models\GuestVerification;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Services\GuestAccess;
use Escalated\Laravel\Tenancy\TenantContext;
use Escalated\Laravel\Tests\Fixtures\TestTenantResolver;
use Illuminate\Support\Facades\Event;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    Event::fake([TicketCreated::class]);
    config(['escalated.tenancy.enabled' => true]);
    $this->resolver = new TestTenantResolver;
    app()->instance(TenantResolver::class, $this->resolver);
    foreach (['merchant-a', 'merchant-b'] as $tenant) {
        app(TenantContext::class)->run($tenant, function () {
            EscalatedSettings::set('guest_tickets_enabled', '1');
            EscalatedSettings::set('widget_enabled', '1');
        });
    }
});

it('binds verification attempts and ticket grants to their merchant on a separate database', function () {
    $context = app(TenantContext::class);
    $proof = $context->run('merchant-a', fn () => $this->guestProof('guest@example.com'));
    $body = $proof + ['name' => 'Guest', 'email' => 'guest@example.com', 'subject' => 'Help', 'description' => 'Details'];
    $this->resolver->selected = 'merchant-b';
    $this->postJson('/support/widget/tickets', $body)->assertUnprocessable();
    expect($context->current())->toBeNull();
    expect($context->run('merchant-b', fn () => GuestVerification::count()))->toBe(0);
    $this->resolver->selected = 'merchant-a';
    $created = $this->postJson('/support/widget/tickets', $body)->assertCreated()->json();
    $this->withToken($created['guest_access_token'])->getJson('/support/widget/tickets/'.$created['reference'])->assertOk();
    $this->resolver->selected = 'merchant-b';
    $this->getJson('/support/widget/tickets/'.$created['reference'])->assertNotFound();
    expect($context->current())->toBeNull();
});

it('returns only the current merchants ticket for a shared email and tracking reference', function () {
    $context = app(TenantContext::class);
    foreach (['merchant-a', 'merchant-b'] as $tenant) {
        $context->run($tenant, fn () => Ticket::factory()->create([
            'requester_type' => null, 'requester_id' => null, 'guest_email' => 'guest@example.com',
            'external_reference' => 'SAME-TRACKING', 'subject' => $tenant,
        ]));
    }
    $proof = $context->run('merchant-b', fn () => $this->guestProof('guest@example.com', 'lookup'));
    $this->resolver->selected = 'merchant-b';
    $result = $this->postJson('/support/widget/lookup', $proof + ['email' => 'guest@example.com', 'reference' => 'SAME-TRACKING'])
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.subject', 'merchant-b')->json('data.0');
    expect($context->run('merchant-b', fn () => app(GuestAccess::class)->resolve($result['guest_access_token'])->subject))->toBe('merchant-b');
    expect(fn () => $context->run('merchant-a', fn () => app(GuestAccess::class)->resolve($result['guest_access_token'])))
        ->toThrow(HttpException::class);
});
