<?php

use Escalated\Laravel\Models\ApiToken;
use Escalated\Laravel\Models\Reply;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Services\AttachmentAccess;
use Escalated\Laravel\Services\AttachmentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    Storage::fake('local');
    Gate::define('escalated-agent', fn ($user) => (bool) $user->is_agent);
    Gate::define('escalated-admin', fn ($user) => (bool) $user->is_admin);
    $this->owner = $this->createTestUser();
    $this->ticket = Ticket::factory()->create([
        'requester_type' => $this->owner->getMorphClass(), 'requester_id' => $this->owner->id,
    ]);
    $this->attachment = app(AttachmentService::class)->store($this->ticket, UploadedFile::fake()->create('parcel.txt', 1, 'text/plain'));
});

it('stores private files and serializes an expiring application URL without storage paths', function () {
    expect($this->attachment->disk)->toBe('local');
    // Flysystem's POSIX mode check cannot represent private ACLs on Windows.
    if (PHP_OS_FAMILY !== 'Windows') {
        expect(Storage::disk('local')->getVisibility($this->attachment->path))->toBe('private');
    }
    $data = $this->attachment->toArray();
    expect($data)->not->toHaveKeys(['disk', 'path'])
        ->and($data['url'])->toContain('/support/attachments/'.$this->attachment->id, 'expires=', 'signature=')
        ->not->toContain('/storage/');
});

it('requires authorization as well as a valid signature and serves downloads without caching', function () {
    $url = $this->attachment->url;
    $this->get($url)->assertForbidden();
    $this->actingAs($this->createTestUser(['email' => 'other@example.test']))->get($url)->assertForbidden();
    $this->actingAs($this->owner)->get($url)->assertOk()->assertDownload('parcel.txt')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Content-Type', 'application/octet-stream')
        ->assertHeader('Cache-Control', 'max-age=0, no-store, private');
});

it('rejects unsigned expired and modified links even for the owner', function () {
    $this->actingAs($this->owner);
    $this->get(route('escalated.attachments.download', $this->attachment))->assertForbidden();
    $this->get(URL::temporarySignedRoute('escalated.attachments.download', now()->subMinute(), ['attachment' => $this->attachment->id]))->assertForbidden();
    $this->get($this->attachment->url.'&guest=forged')->assertForbidden();
    $url = $this->attachment->url;
    $this->travel(11)->minutes();
    $this->get($url)->assertForbidden();
});

it('protects internal notes from requesters while allowing authorized agents', function () {
    $reply = Reply::factory()->create(['ticket_id' => $this->ticket->id, 'is_internal_note' => true]);
    $attachment = app(AttachmentService::class)->store($reply, UploadedFile::fake()->create('note.txt', 1));
    $this->actingAs($this->owner)->get($attachment->url)->assertForbidden();
    $this->actingAs($this->createAgent())->get($attachment->url)->assertOk();
});

it('authenticates API bearer downloads and checks current ticket permissions', function () {
    $token = ApiToken::createToken($this->owner, 'Mobile')['plainTextToken'];
    $this->withToken($token)->get($this->attachment->url)->assertOk();
    $this->withToken('invalid')->getJson($this->attachment->url)->assertUnauthorized();
});

it('keeps customer bearer tokens within requester access even when their owner is an agent', function () {
    $agent = $this->createAgent();
    $customerToken = ApiToken::createToken($agent, 'Customer app', ['customer'])['plainTextToken'];
    $this->withToken($customerToken)->get($this->attachment->url)->assertForbidden();
    $this->ticket->update(['requester_id' => $agent->id, 'requester_type' => $agent->getMorphClass()]);
    $this->withToken($customerToken)->get($this->attachment->url)->assertOk();
    $emptyToken = ApiToken::createToken($agent, 'No abilities', [])['plainTextToken'];
    $this->withToken($emptyToken)->get($this->attachment->url)->assertForbidden();
});

it('allows only ticket-bound public guest grants and revokes them on token rotation', function () {
    $this->ticket->update(['guest_token' => str_repeat('a', 64)]);
    app(AttachmentAccess::class)->forGuest($this->ticket);
    $url = $this->attachment->fresh()->url;
    expect($url)->toContain('guest=')->not->toContain(str_repeat('a', 64));
    $this->get($url)->assertOk();
    $this->ticket->update(['guest_token' => str_repeat('b', 64)]);
    $this->get($url)->assertForbidden();
});

it('does not issue a guest grant for another ticket or an internal note', function () {
    $guest = Ticket::factory()->create(['guest_token' => str_repeat('g', 64)]);
    app(AttachmentAccess::class)->forGuest($guest);
    expect($this->attachment->url)->not->toContain('guest=');
    $reply = Reply::factory()->create(['ticket_id' => $guest->id, 'is_internal_note' => true]);
    $note = app(AttachmentService::class)->store($reply, UploadedFile::fake()->create('note.txt', 1));
    expect($note->url)->not->toContain('guest=');
    // Even a host-issued URL carrying a guest grant cannot expose an internal note.
    $url = URL::temporarySignedRoute('escalated.attachments.download', now()->addMinute(), [
        'attachment' => $note->id, 'guest' => hash('sha256', $guest->guest_token),
    ]);
    $this->get($url)->assertForbidden();
});

it('refuses orphaned attachments and files that no longer exist', function () {
    $this->actingAs($this->owner);
    Storage::disk('local')->delete($this->attachment->path);
    $this->get($this->attachment->url)->assertNotFound();
    $this->ticket->delete();
    $this->get($this->attachment->url)->assertNotFound();
});

it('sanitizes download filenames supplied by clients', function () {
    $this->attachment->update(['original_filename' => "../../parcel\r\n.txt"]);
    $this->actingAs($this->owner)->get($this->attachment->url)->assertOk()->assertDownload('parcel.txt');
});

it('uses the configured private disk', function () {
    Storage::fake('merchant-private');
    config(['escalated.storage.disk' => 'merchant-private']);
    $attachment = app(AttachmentService::class)->store($this->ticket, UploadedFile::fake()->create('private.txt', 1));
    expect($attachment->disk)->toBe('merchant-private');
    Storage::disk('merchant-private')->assertExists($attachment->path);
    $this->actingAs($this->owner)->get($attachment->url)->assertOk();
});

it('resolves a nondefault host guard and configured agent gates without admitting anonymous users', function () {
    config([
        'auth.providers.staff' => ['driver' => 'eloquent', 'model' => get_class($this->owner)],
        'auth.guards.staff' => ['driver' => 'session', 'provider' => 'staff'],
        'escalated.storage.download_guard' => 'staff',
        'escalated.authorization.agent_gate' => 'host-support-agent',
    ]);
    Gate::define('escalated-agent', fn () => false);
    Gate::define('host-support-agent', fn ($user) => (bool) $user->is_agent);
    $this->get($this->attachment->url)->assertForbidden();
    $agent = $this->createAgent();
    $this->withSession([Auth::guard('staff')->getName() => $agent->id])
        ->get($this->attachment->url)->assertOk();
});
