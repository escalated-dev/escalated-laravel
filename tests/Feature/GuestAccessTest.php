<?php

use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Services\GuestAccess;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->guest = Ticket::factory()->create([
        'requester_type' => null, 'requester_id' => null,
        'guest_email' => 'guest@example.com', 'guest_verified_email' => 'guest@example.com',
        'guest_email_verified_at' => now(),
    ]);
});

it('issues a sealed expiring grant and stores only its nonce hash', function () {
    $access = app(GuestAccess::class);
    $token = $access->issue($this->guest);
    expect($access->resolve($token)->id)->toBe($this->guest->id);
    expect($this->guest->fresh()->guest_token)->toBeNull();
    expect($this->guest->guest_access_hash)->not->toBe($token);
    expect($token)->not->toContain('guest@example.com');
    expect($this->guest->toArray())->not->toHaveKeys(['guest_access_hash', 'guest_token', 'guest_verified_email']);
});

it('rejects legacy, tampered, expired and wrong-purpose tokens', function () {
    $access = app(GuestAccess::class);
    $token = $access->issue($this->guest);
    expect(fn () => $access->resolve(str_repeat('a', 64)))->toThrow(HttpException::class);
    expect(fn () => $access->resolve(substr_replace($token, $token[12] === 'A' ? 'B' : 'A', 12, 1)))->toThrow(HttpException::class);
    expect(fn () => $access->resolve($token, 'chat'))->toThrow(HttpException::class);
    $this->travel(24)->hours();
    expect(fn () => $access->resolve($token))->toThrow(HttpException::class);
});

it('revokes previous links on rotation, explicit revocation and an email change', function () {
    $access = app(GuestAccess::class);
    $first = $access->issue($this->guest);
    $second = $access->issue($this->guest);
    expect(fn () => $access->resolve($first))->toThrow(HttpException::class);
    expect($access->resolve($second)->id)->toBe($this->guest->id);
    $access->revoke($this->guest);
    expect(fn () => $access->resolve($second))->toThrow(HttpException::class);
    $third = $access->issue($this->guest);
    $this->guest->updateQuietly(['guest_email' => 'other@example.com']);
    $this->guest->updateQuietly(['guest_email' => 'guest@example.com']);
    expect(fn () => $access->resolve($third))->toThrow(HttpException::class);
    expect($this->guest->fresh()->guest_email_verified_at)->toBeNull();
});

it('will not issue access before proof of the exact email', function () {
    $this->guest->updateQuietly(['guest_email_verified_at' => null]);
    expect(fn () => app(GuestAccess::class)->issue($this->guest))->toThrow(LogicException::class);
});
