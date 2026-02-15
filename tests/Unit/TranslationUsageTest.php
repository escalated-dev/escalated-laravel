<?php

use Escalated\Laravel\Enums\ActivityType;
use Escalated\Laravel\Enums\TicketPriority;
use Escalated\Laravel\Enums\TicketStatus;
use Illuminate\Support\Facades\App;

it('translates ticket status labels based on locale', function () {
    App::setLocale('en');
    expect(TicketStatus::Open->label())->toBe('Open');
    expect(TicketStatus::InProgress->label())->toBe('In Progress');
    expect(TicketStatus::WaitingOnCustomer->label())->toBe('Waiting on Customer');

    App::setLocale('es');
    expect(TicketStatus::Open->label())->toBe('Abierto');
    expect(TicketStatus::InProgress->label())->toBe('En progreso');
    expect(TicketStatus::WaitingOnCustomer->label())->toBe('Esperando al cliente');

    App::setLocale('fr');
    expect(TicketStatus::Open->label())->toBe('Ouvert');
    expect(TicketStatus::InProgress->label())->toBe('En cours');

    App::setLocale('de');
    expect(TicketStatus::Open->label())->toBe('Offen');
    expect(TicketStatus::InProgress->label())->toBe('In Bearbeitung');
});

it('translates ticket priority labels based on locale', function () {
    App::setLocale('en');
    expect(TicketPriority::Low->label())->toBe('Low');
    expect(TicketPriority::Critical->label())->toBe('Critical');

    App::setLocale('es');
    expect(TicketPriority::Low->label())->toBe('Bajo');
    expect(TicketPriority::Critical->label())->toBe('Crítico');

    App::setLocale('fr');
    expect(TicketPriority::Low->label())->toBe('Bas');
    expect(TicketPriority::Critical->label())->toBe('Critique');

    App::setLocale('de');
    expect(TicketPriority::Low->label())->toBe('Niedrig');
    expect(TicketPriority::Critical->label())->toBe('Kritisch');
});

it('translates activity type labels based on locale', function () {
    App::setLocale('en');
    expect(ActivityType::SlaBreached->label())->toBe('SLA Breached');
    expect(ActivityType::StatusChanged->label())->toBe('Status Changed');

    App::setLocale('es');
    expect(ActivityType::SlaBreached->label())->toBe('SLA incumplido');
    expect(ActivityType::StatusChanged->label())->toBe('Estado cambiado');

    App::setLocale('fr');
    expect(ActivityType::SlaBreached->label())->toBe('SLA non respecté');

    App::setLocale('de');
    expect(ActivityType::SlaBreached->label())->toBe('SLA verletzt');
});

it('returns all status labels for each locale', function () {
    foreach (['en', 'es', 'fr', 'de'] as $locale) {
        App::setLocale($locale);

        foreach (TicketStatus::cases() as $status) {
            $label = $status->label();
            expect($label)->toBeString();
            expect($label)->not->toContain('escalated::');
        }
    }
});

it('returns all priority labels for each locale', function () {
    foreach (['en', 'es', 'fr', 'de'] as $locale) {
        App::setLocale($locale);

        foreach (TicketPriority::cases() as $priority) {
            $label = $priority->label();
            expect($label)->toBeString();
            expect($label)->not->toContain('escalated::');
        }
    }
});

it('returns all activity type labels for each locale', function () {
    foreach (['en', 'es', 'fr', 'de'] as $locale) {
        App::setLocale($locale);

        foreach (ActivityType::cases() as $type) {
            $label = $type->label();
            expect($label)->toBeString();
            expect($label)->not->toContain('escalated::');
        }
    }
});

it('fixes SLA breached casing issue', function () {
    App::setLocale('en');
    expect(ActivityType::SlaBreached->label())->toBe('SLA Breached');
});
