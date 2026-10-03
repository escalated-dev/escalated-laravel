<?php

use Escalated\Laravel\Escalated;
use Escalated\Laravel\Mail\GuestVerificationCode;
use Escalated\Laravel\Models\EscalatedSettings;
use Escalated\Laravel\Models\GuestVerification;
use Escalated\Laravel\Services\GuestEmailVerification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    Mail::fake();
    EscalatedSettings::set('guest_tickets_enabled', '1');
});

it('delivers a code only by email and stores neither the code nor plaintext email', function () {
    $id = app(GuestEmailVerification::class)->challenge(' Guest@Example.com ', 'ticket');
    $row = Escalated::db()->table(Escalated::table('guest_verifications'))->where('id', $id)->first();
    Mail::assertSent(GuestVerificationCode::class, function ($mail) use ($row) {
        expect($mail->code)->toMatch('/^[0-9]{8}$/');
        expect($row->code_hash)->not->toBe($mail->code);
        expect($row->email)->not->toContain('guest@example.com');

        return $mail->hasTo('guest@example.com');
    });
    expect(GuestVerification::find($id)->toArray())->not->toHaveKeys(['email', 'code_hash']);
});

it('consumes a code once and binds it to email and purpose', function () {
    $service = app(GuestEmailVerification::class);
    $id = $service->challenge('guest@example.com', 'ticket');
    $code = Mail::sent(GuestVerificationCode::class)->first()->code;
    expect(fn () => $service->consume($id, $code, 'other@example.com', 'ticket', fn () => 'bad'))->toThrow(ValidationException::class);
    expect(fn () => $service->consume($id, $code, 'guest@example.com', 'chat', fn () => 'bad'))->toThrow(ValidationException::class);
    expect($service->consume($id, $code, 'GUEST@example.com', 'ticket', fn () => 'verified'))->toBe('verified');
    expect(fn () => $service->consume($id, $code, 'guest@example.com', 'ticket', fn () => 'bad'))->toThrow(ValidationException::class);
});

it('persists failed attempts and refuses a correct code after five guesses', function () {
    $service = app(GuestEmailVerification::class);
    $id = $service->challenge('guest@example.com', 'ticket');
    $code = Mail::sent(GuestVerificationCode::class)->first()->code;
    for ($i = 0; $i < 5; $i++) {
        expect(fn () => $service->consume($id, 'incorrect', 'guest@example.com', 'ticket', fn () => true))->toThrow(ValidationException::class);
    }
    expect(GuestVerification::find($id)->attempts)->toBe(5);
    expect(fn () => $service->consume($id, $code, 'guest@example.com', 'ticket', fn () => true))->toThrow(ValidationException::class);
});

it('expires codes and rolls back consumption if the authorized operation fails', function () {
    $service = app(GuestEmailVerification::class);
    $id = $service->challenge('guest@example.com', 'ticket');
    $code = Mail::sent(GuestVerificationCode::class)->first()->code;
    expect(fn () => $service->consume($id, $code, 'guest@example.com', 'ticket', fn () => throw new RuntimeException('failed')))->toThrow(RuntimeException::class);
    expect(GuestVerification::find($id)->used_at)->toBeNull();
    $this->travel(11)->minutes();
    expect(fn () => $service->consume($id, $code, 'guest@example.com', 'ticket', fn () => true))->toThrow(ValidationException::class);
});

it('limits email challenges per mailbox and client IP', function () {
    $service = app(GuestEmailVerification::class);
    for ($i = 0; $i < 3; $i++) {
        $service->challenge('guest@example.com', 'ticket', '198.51.100.7');
    }
    expect(fn () => $service->challenge('GUEST@example.com', 'chat', '198.51.100.7'))->toThrow(HttpException::class);
    Mail::assertSentCount(3);
});

it('does not let one client lock the mailbox owner out of new codes', function () {
    $service = app(GuestEmailVerification::class);
    for ($i = 0; $i < 3; $i++) {
        $service->challenge('guest@example.com', 'ticket', '198.51.100.7');
    }
    expect(fn () => $service->challenge('guest@example.com', 'ticket', '198.51.100.7'))->toThrow(HttpException::class);

    expect($service->challenge('guest@example.com', 'ticket', '203.0.113.20'))->toBeString();
    Mail::assertSentCount(4);
});

it('caps email challenges per mailbox across client IPs', function () {
    $service = app(GuestEmailVerification::class);
    for ($i = 0; $i < 10; $i++) {
        $service->challenge('guest@example.com', 'ticket', '198.51.100.'.$i);
    }
    expect(fn () => $service->challenge('guest@example.com', 'ticket', '198.51.100.200'))->toThrow(HttpException::class);
    Mail::assertSentCount(10);
});

it('keys the HTTP challenge budget on the requesting client IP', function () {
    for ($i = 0; $i < 3; $i++) {
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
            ->postJson('/support/guest/verification', ['email' => 'guest@example.com', 'purpose' => 'ticket'])
            ->assertAccepted();
    }
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
        ->postJson('/support/guest/verification', ['email' => 'guest@example.com', 'purpose' => 'ticket'])
        ->assertStatus(429)->assertHeader('Retry-After');
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.20'])
        ->postJson('/support/guest/verification', ['email' => 'guest@example.com', 'purpose' => 'ticket'])
        ->assertAccepted();
});
