<?php

use Escalated\Laravel\Models\Attachment;
use Escalated\Laravel\Models\AttachmentMigration;
use Escalated\Laravel\Models\Ticket;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
    $ticket = Ticket::factory()->create();
    $this->attachment = Attachment::create([
        'attachable_type' => $ticket->getMorphClass(), 'attachable_id' => $ticket->id,
        'filename' => 'parcel.jpg', 'original_filename' => 'parcel.jpg', 'mime_type' => 'image/jpeg',
        'size' => 12, 'disk' => 'public', 'path' => 'escalated/attachments/parcel.jpg',
    ]);
    Storage::disk('public')->put($this->attachment->path, 'parcel bytes');
});

it('defaults to a dry run without touching records or files', function () {
    $this->artisan('escalated:attachments:privatize')->assertSuccessful();
    expect($this->attachment->fresh()->disk)->toBe('public');
    Storage::disk('public')->assertExists($this->attachment->path);
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

it('copies and verifies bytes before updating duplicate references and deleting the public original', function () {
    $duplicate = $this->attachment->replicate();
    $duplicate->save();
    $oldPath = $this->attachment->path;
    $this->artisan('escalated:attachments:privatize', ['--apply' => true])->assertSuccessful();
    foreach ([$this->attachment, $duplicate] as $attachment) {
        $attachment->refresh();
        expect($attachment->disk)->toBe('local')
            ->and(Storage::disk('local')->get($attachment->path))->toBe('parcel bytes');
    }
    Storage::disk('public')->assertMissing($oldPath);
    $this->artisan('escalated:attachments:privatize', ['--apply' => true])->assertSuccessful();
    expect(Storage::disk('local')->allFiles())->toHaveCount(2);
});

it('retains records when a source cannot be copied and returns failure', function () {
    Storage::disk('public')->delete($this->attachment->path);
    $this->artisan('escalated:attachments:privatize', ['--apply' => true])->assertFailed();
    expect($this->attachment->fresh()->disk)->toBe('public')
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('rejects a public or identical destination disk', function () {
    $this->artisan('escalated:attachments:privatize', ['--to' => 'public', '--apply' => true])->assertFailed();
    expect($this->attachment->fresh()->disk)->toBe('public');
    Storage::disk('public')->assertExists($this->attachment->path);
});

it('retries public deletion from its journal after the attachment record has moved', function () {
    $disk = Storage::disk('public');
    $failing = Mockery::mock($disk);
    $failing->shouldReceive('delete')->andReturn(false);
    Storage::set('public', $failing);
    $oldPath = $this->attachment->path;
    $this->artisan('escalated:attachments:privatize', ['--apply' => true])->assertFailed();
    expect($this->attachment->fresh()->disk)->toBe('local')
        ->and(AttachmentMigration::sole()->status)->toBe('moved');
    $disk->assertExists($oldPath);
    Storage::set('public', $disk);
    $this->artisan('escalated:attachments:privatize', ['--apply' => true])->assertSuccessful();
    $disk->assertMissing($oldPath);
    expect(AttachmentMigration::count())->toBe(0);
});

it('recovers a process interruption after copying but before switching the reference', function () {
    Storage::disk('local')->put('abandoned-copy', 'parcel bytes', 'private');
    AttachmentMigration::create([
        'attachment_id' => $this->attachment->id,
        'source_disk' => 'public', 'source_path' => $this->attachment->path,
        'destination_disk' => 'local', 'destination_path' => 'abandoned-copy',
    ]);
    $this->artisan('escalated:attachments:privatize', ['--apply' => true])->assertSuccessful();
    Storage::disk('local')->assertMissing('abandoned-copy');
    expect($this->attachment->fresh()->disk)->toBe('local')
        ->and(AttachmentMigration::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toHaveCount(1);
});

it('removes unused private copies when checksum verification throws and keeps the public source', function () {
    $disk = Storage::disk('local');
    $failing = Mockery::mock($disk);
    $failing->shouldReceive('readStream')->andThrow(new RuntimeException('Cannot verify'));
    Storage::set('local', $failing);
    $this->artisan('escalated:attachments:privatize', ['--apply' => true])->assertFailed();
    expect($this->attachment->fresh()->disk)->toBe('public')
        ->and($disk->allFiles())->toBe([])
        ->and(AttachmentMigration::count())->toBe(0);
    Storage::disk('public')->assertExists($this->attachment->path);
});

it('retains files when a concurrent edit changes the attachment record', function () {
    $disk = Storage::disk('local');
    $racing = Mockery::mock($disk);
    $racing->shouldReceive('put')->andReturnUsing(function ($path, $content, $options) use ($disk) {
        $this->attachment->update(['path' => 'concurrently-changed']);

        return $disk->put($path, $content, $options);
    });
    Storage::set('local', $racing);
    $oldPath = $this->attachment->path;
    $this->artisan('escalated:attachments:privatize', ['--apply' => true])->assertFailed();
    expect($this->attachment->fresh()->path)->toBe('concurrently-changed')
        ->and($disk->allFiles())->toHaveCount(1)
        ->and(AttachmentMigration::sole()->status)->toBe('conflict');
    Storage::disk('public')->assertExists($oldPath);
});
