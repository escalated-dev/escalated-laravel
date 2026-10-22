<?php

/*
 * An import adapter is third-party code talking to a remote platform. The
 * attachment records it returns must not be able to point an Escalated
 * attachment at an arbitrary file: the importer stores the bytes the adapter
 * sends, or accepts a path only inside the configured import directory on
 * the import disk.
 */

use Escalated\Laravel\Contracts\ImportAdapter;
use Escalated\Laravel\Models\Attachment;
use Escalated\Laravel\Models\ImportJob;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Services\ImportService;
use Escalated\Laravel\Support\ExtractResult;
use Illuminate\Support\Facades\Storage;

function importPathAdapter(array $attachments): ImportAdapter
{
    return new class($attachments) implements ImportAdapter
    {
        public function __construct(private array $attachments) {}

        public function name(): string
        {
            return 'evil';
        }

        public function displayName(): string
        {
            return 'Evil';
        }

        public function credentialFields(): array
        {
            return [];
        }

        public function testConnection(array $credentials): bool
        {
            return true;
        }

        public function entityTypes(): array
        {
            return ['tickets', 'attachments'];
        }

        public function defaultFieldMappings(string $entityType): array
        {
            return [];
        }

        public function availableSourceFields(string $entityType, array $credentials): array
        {
            return [];
        }

        public function extract(string $entityType, array $credentials, ?string $cursor): ExtractResult
        {
            return match ($entityType) {
                'tickets' => new ExtractResult([['source_id' => 't1', 'title' => 'Imported']], null),
                'attachments' => new ExtractResult($this->attachments, null),
                default => new ExtractResult([], null),
            };
        }
    };
}

function runImportWith(array $attachments): ImportJob
{
    $adapter = importPathAdapter($attachments);
    escalated_add_filter('import.adapters', fn (array $adapters) => [...$adapters, $adapter]);

    $job = ImportJob::create(['user_id' => test()->createTestUser()->getKey(), 'platform' => 'evil', 'status' => 'mapping', 'credentials' => ['k' => 'v']]);
    app(ImportService::class)->run($job);

    return $job->refresh();
}

function attachmentRecord(string $sourceId, array $fields): array
{
    return ['source_id' => $sourceId, 'parent_type' => 'ticket', 'parent_source_id' => 't1', 'filename' => 'file.txt'] + $fields;
}

beforeEach(function () {
    Storage::fake('local');
    Storage::disk('local')->put('.env', 'APP_KEY=secret');
    Storage::disk('local')->put('escalated/attachments/other-tenant.pdf', 'private');
    Storage::disk('local')->put('escalated/imports/zendesk/123.txt', 'imported bytes');
});

it('rejects adapter paths that escape the import directory', function (string $path) {
    $job = runImportWith([attachmentRecord('a1', ['path' => $path])]);

    expect(Attachment::count())->toBe(0)
        ->and($job->progress['attachments']['failed'])->toBe(1)
        ->and($job->error_log[0]['error'])->toContain('import directory');
})->with([
    'host file on the disk' => ['.env'],
    'another attachment' => ['escalated/attachments/other-tenant.pdf'],
    'traversal out of the import directory' => ['escalated/imports/../../.env'],
    'traversal hidden in a segment' => ['escalated/imports/zendesk/../../attachments/other-tenant.pdf'],
    'absolute path' => ['/etc/passwd'],
    'absolute path under the prefix' => ['/escalated/imports/zendesk/123.txt'],
    'windows drive path' => ['C:\\Windows\\win.ini'],
    'backslash traversal' => ['escalated\\imports\\..\\..\\.env'],
    'stream wrapper' => ['file:///etc/passwd'],
    'directory prefix lookalike' => ['escalated/imports-evil/x.txt'],
    'null byte' => ["escalated/imports/zendesk/123.txt\0.png"],
]);

it('rejects a path inside the import directory that does not exist', function () {
    $job = runImportWith([attachmentRecord('a1', ['path' => 'escalated/imports/zendesk/missing.txt'])]);

    expect(Attachment::count())->toBe(0)
        ->and($job->error_log[0]['error'])->toContain('does not exist');
});

it('accepts a path to a file the adapter stored in the import directory', function () {
    $job = runImportWith([attachmentRecord('a1', ['path' => 'escalated/imports/zendesk/123.txt'])]);

    $attachment = Attachment::sole();
    expect($job->status)->toBe('completed')
        ->and($attachment->path)->toBe('escalated/imports/zendesk/123.txt')
        ->and($attachment->disk)->toBe('local');
});

it('honours a configured import directory', function () {
    config(['escalated.attachments.import_path' => 'migrations/legacy']);
    Storage::disk('local')->put('migrations/legacy/a.txt', 'legacy');

    runImportWith([
        attachmentRecord('a1', ['path' => 'migrations/legacy/a.txt']),
        attachmentRecord('a2', ['path' => 'escalated/imports/zendesk/123.txt']),
    ]);

    expect(Attachment::pluck('path')->all())->toBe(['migrations/legacy/a.txt']);
});

it('stores the bytes an adapter sends as a new private attachment', function () {
    $job = runImportWith([attachmentRecord('a1', ['content' => 'hello world', 'path' => '.env'])]);

    $attachment = Attachment::sole();
    expect($job->status)->toBe('completed')
        ->and($attachment->path)->not->toBe('.env')
        ->and($attachment->path)->toStartWith('escalated/attachments/')
        ->and($attachment->original_filename)->toBe('file.txt')
        ->and($attachment->size)->toBe(11)
        ->and(Storage::disk($attachment->disk)->get($attachment->path))->toBe('hello world');
});

it('rejects content over the attachment size limit', function () {
    config(['escalated.tickets.max_attachment_size_kb' => 1]);

    $job = runImportWith([attachmentRecord('a1', ['content' => str_repeat('x', 2048)])]);

    expect(Attachment::count())->toBe(0)
        ->and($job->error_log[0]['error'])->toContain('size limit');
});

it('fails a record with neither content nor a path', function () {
    $job = runImportWith([attachmentRecord('a1', [])]);

    expect(Attachment::count())->toBe(0)
        ->and($job->progress['attachments']['failed'])->toBe(1);
});

it('imports the ticket subject the adapter sends as title', function () {
    runImportWith([]);

    expect(Ticket::sole()->subject)->toBe('Imported');
});
