<?php

use Escalated\Laravel\Enums\TicketPriority;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Notifications\NewTicketNotification;
use Escalated\Laravel\Notifications\SlaBreachNotification;
use Escalated\Laravel\Notifications\TicketAssignedNotification;
use Escalated\Laravel\Notifications\TicketEscalatedNotification;
use Escalated\Laravel\Notifications\TicketResolvedNotification;
use Escalated\Laravel\Notifications\TicketStatusChangedNotification;
use Illuminate\Support\Facades\App;

beforeEach(function () {
    $this->user = $this->createTestUser();
    $this->ticket = Ticket::factory()->forRequester(get_class($this->user), $this->user->id)->create([
        'reference' => 'TK-001',
        'subject' => 'Test Ticket',
    ]);
});

it('translates new ticket notification subject', function () {
    $notification = new NewTicketNotification($this->ticket);

    App::setLocale('en');
    $mailEn = $notification->toMail($this->user);
    expect($mailEn->subject)->toContain('New Ticket');

    App::setLocale('es');
    $mailEs = $notification->toMail($this->user);
    expect($mailEs->subject)->toContain('Nuevo ticket');

    App::setLocale('fr');
    $mailFr = $notification->toMail($this->user);
    expect($mailFr->subject)->toContain('Nouveau ticket');

    App::setLocale('de');
    $mailDe = $notification->toMail($this->user);
    expect($mailDe->subject)->toContain('Neues Ticket');
});

it('translates ticket assigned notification subject', function () {
    $notification = new TicketAssignedNotification($this->ticket);

    App::setLocale('en');
    $mail = $notification->toMail($this->user);
    expect($mail->subject)->toContain('Assigned to You');

    App::setLocale('es');
    $mail = $notification->toMail($this->user);
    expect($mail->subject)->toContain('asignado a usted');
});

it('translates ticket resolved notification subject', function () {
    $notification = new TicketResolvedNotification($this->ticket);

    App::setLocale('en');
    $mail = $notification->toMail($this->user);
    expect($mail->subject)->toContain('Ticket Resolved');

    App::setLocale('de');
    $mail = $notification->toMail($this->user);
    expect($mail->subject)->toContain('Ticket gelöst');
});

it('translates status changed notification subject', function () {
    $notification = new TicketStatusChangedNotification(
        $this->ticket,
        TicketStatus::Open,
        TicketStatus::InProgress,
    );

    App::setLocale('en');
    $mail = $notification->toMail($this->user);
    expect($mail->subject)->toContain('Status Updated');

    App::setLocale('fr');
    $mail = $notification->toMail($this->user);
    expect($mail->subject)->toContain('Statut mis à jour');
});

it('translates SLA breach notification subject', function () {
    $notification = new SlaBreachNotification($this->ticket, 'first_response');

    App::setLocale('en');
    $mail = $notification->toMail($this->user);
    expect($mail->subject)->toContain('SLA Breach');
    expect($mail->subject)->toContain('First Response');

    App::setLocale('es');
    $mail = $notification->toMail($this->user);
    expect($mail->subject)->toContain('Incumplimiento SLA');
    expect($mail->subject)->toContain('Primera respuesta');
});

it('translates escalated notification subject', function () {
    $notification = new TicketEscalatedNotification($this->ticket, 'High priority');

    App::setLocale('en');
    $mail = $notification->toMail($this->user);
    expect($mail->subject)->toContain('Escalated');

    App::setLocale('de');
    $mail = $notification->toMail($this->user);
    expect($mail->subject)->toContain('Eskaliert');
});

it('translates notification action text', function () {
    $notification = new NewTicketNotification($this->ticket);

    App::setLocale('en');
    $mail = $notification->toMail($this->user);
    expect($mail->actionText)->toBe('View Ticket');

    App::setLocale('es');
    $mail = $notification->toMail($this->user);
    expect($mail->actionText)->toBe('Ver ticket');

    App::setLocale('fr');
    $mail = $notification->toMail($this->user);
    expect($mail->actionText)->toBe('Voir le ticket');

    App::setLocale('de');
    $mail = $notification->toMail($this->user);
    expect($mail->actionText)->toBe('Ticket ansehen');
});

it('does not translate toArray output', function () {
    $notification = new NewTicketNotification($this->ticket);

    App::setLocale('es');
    $array = $notification->toArray($this->user);

    expect($array['type'])->toBe('new_ticket');
    expect($array['reference'])->toBe('TK-001');
});
