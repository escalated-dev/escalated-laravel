<?php

namespace Escalated\Laravel\Services;

use Escalated\Laravel\Contracts\ImportAdapter;
use Escalated\Laravel\Enums\TicketPriority;
use Escalated\Laravel\Enums\TicketStatus;
use Escalated\Laravel\Models\Attachment;
use Escalated\Laravel\Models\CustomField;
use Escalated\Laravel\Models\Department;
use Escalated\Laravel\Models\ImportJob;
use Escalated\Laravel\Models\ImportSourceMap;
use Escalated\Laravel\Models\Reply;
use Escalated\Laravel\Models\SatisfactionRating;
use Escalated\Laravel\Models\Tag;
use Escalated\Laravel\Models\Ticket;
use Escalated\Laravel\Support\ImportContext;

class ImportService
{
    /**
     * Resolve all registered import adapters from the plugin hook.
     *
     * @return ImportAdapter[]
     */
    public function availableAdapters(): array
    {
        return escalated_apply_filters('import.adapters', []);
    }

    public function resolveAdapter(string $platform): ?ImportAdapter
    {
        foreach ($this->availableAdapters() as $adapter) {
            if ($adapter->name() === $platform) {
                return $adapter;
            }
        }

        return null;
    }

    public function testConnection(ImportJob $job): bool
    {
        $adapter = $this->resolveAdapter($job->platform);

        if (! $adapter) {
            throw new \RuntimeException("No adapter found for platform '{$job->platform}'.");
        }

        return $adapter->testConnection($job->credentials);
    }

    /**
     * Run the import for a job. Called by both UI and CLI.
     *
     * @param  callable|null  $onProgress  Called after each batch: fn(string $entityType, array $progressData)
     */
    public function run(ImportJob $job, ?callable $onProgress = null): void
    {
        $adapter = $this->resolveAdapter($job->platform);

        if (! $adapter) {
            $job->update(['status' => 'failed']);
            throw new \RuntimeException("No adapter found for platform '{$job->platform}'.");
        }

        // Only transition if not already importing (supports resume)
        if ($job->status !== 'importing') {
            $job->transitionTo('importing');
        }
        $job->update(['started_at' => $job->started_at ?? now()]);

        // Pass job ID to adapter for cross-referencing (needed for reply extraction)
        if (method_exists($adapter, 'setJobId')) {
            $adapter->setJobId($job->id);
        }

        ImportContext::suppress(function () use ($job, $adapter, $onProgress) {
            foreach ($adapter->entityTypes() as $entityType) {
                // Check for pause between entity types
                $job->refresh();
                if ($job->status === 'paused') {
                    return;
                }

                $this->importEntityType($job, $adapter, $entityType, $onProgress);
            }
        });

        $job->refresh();

        if ($job->status === 'paused') {
            return; // Paused mid-import, don't mark completed
        }

        $job->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $job->purgeCredentials();

        // Dispatch post-import summary event for listeners (e.g., reindexing)
        escalated_do_action('import.completed', $job);
    }

    private function importEntityType(
        ImportJob $job,
        ImportAdapter $adapter,
        string $entityType,
        ?callable $onProgress,
    ): void {
        $cursor = $job->getEntityCursor($entityType);
        $processed = $job->progress[$entityType]['processed'] ?? 0;
        $skipped = $job->progress[$entityType]['skipped'] ?? 0;
        $failed = $job->progress[$entityType]['failed'] ?? 0;

        do {
            $result = $adapter->extract($entityType, $job->credentials, $cursor);

            if ($result->totalCount !== null) {
                $job->updateEntityProgress($entityType, total: $result->totalCount);
            }

            foreach ($result->records as $record) {
                $sourceId = $record['source_id'] ?? null;

                if (! $sourceId) {
                    $failed++;

                    continue;
                }

                // Skip already-imported records (resumability)
                if (ImportSourceMap::hasBeenImported($job->id, $entityType, $sourceId)) {
                    $skipped++;

                    continue;
                }

                try {
                    $escalatedId = $this->persistRecord($job, $entityType, $record);

                    ImportSourceMap::create([
                        'import_job_id' => $job->id,
                        'entity_type' => $entityType,
                        'source_id' => $sourceId,
                        'escalated_id' => (string) $escalatedId,
                    ]);

                    $processed++;
                } catch (\Throwable $e) {
                    $failed++;
                    $job->appendError($entityType, $sourceId, $e->getMessage());
                }
            }

            $cursor = $result->cursor;

            $job->updateEntityProgress(
                $entityType,
                processed: $processed,
                skipped: $skipped,
                failed: $failed,
                cursor: $cursor,
            );

            if ($onProgress) {
                $onProgress($entityType, $job->progress[$entityType]);
            }

            // Check for pause between batches
            $job->refresh();
            if ($job->status === 'paused') {
                return;
            }

        } while (! $result->isExhausted());
    }

    /**
     * Persist a single normalized record into Escalated.
     * Returns the Escalated model's ID.
     */
    private function persistRecord(ImportJob $job, string $entityType, array $record): string|int
    {
        $mappings = $job->field_mappings[$entityType] ?? [];

        return match ($entityType) {
            'agents' => $this->persistAgent($record, $mappings),
            'tags' => $this->persistTag($record, $mappings),
            'custom_fields' => $this->persistCustomField($record, $mappings),
            'contacts' => $this->persistContact($record, $mappings),
            'departments' => $this->persistDepartment($record, $mappings),
            'tickets' => $this->persistTicket($job, $record, $mappings),
            'replies' => $this->persistReply($job, $record, $mappings),
            'attachments' => $this->persistAttachment($job, $record, $mappings),
            'satisfaction_ratings' => $this->persistSatisfactionRating($job, $record, $mappings),
            default => throw new \RuntimeException("Unknown entity type: {$entityType}"),
        };
    }

    private function persistTag(array $record, array $mappings): string|int
    {
        $tag = Tag::firstOrCreate(
            ['slug' => \Str::slug($record['name'])],
            ['name' => $record['name']],
        );

        return $tag->getKey();
    }

    private function persistAgent(array $record, array $mappings): string|int
    {
        $userModel = config('escalated.user_model', 'App\\Models\\User');
        $user = $userModel::where('email', $record['email'])->first();

        if (! $user) {
            throw new \RuntimeException("Agent with email '{$record['email']}' not found in host application.");
        }

        return $user->getKey();
    }

    private function persistContact(array $record, array $mappings): string|int
    {
        $userModel = config('escalated.user_model', 'App\\Models\\User');

        $user = $userModel::firstOrCreate(
            ['email' => $record['email']],
            ['name' => $record['name'] ?? $record['email']],
        );

        return $user->getKey();
    }

    private function persistDepartment(array $record, array $mappings): string|int
    {
        $dept = Department::firstOrCreate(
            ['slug' => \Str::slug($record['name'])],
            ['name' => $record['name'], 'is_active' => true],
        );

        return $dept->getKey();
    }

    private function persistTicket(ImportJob $job, array $record, array $mappings): string|int
    {
        $requesterId = null;
        if (! empty($record['requester_source_id'])) {
            $requesterId = ImportSourceMap::resolve($job->id, 'contacts', $record['requester_source_id']);
        }

        $assigneeId = null;
        if (! empty($record['assignee_source_id'])) {
            $assigneeId = ImportSourceMap::resolve($job->id, 'agents', $record['assignee_source_id']);
        }

        $departmentId = null;
        if (! empty($record['department_source_id'])) {
            $departmentId = ImportSourceMap::resolve($job->id, 'departments', $record['department_source_id']);
        }

        $userModel = config('escalated.user_model', 'App\\Models\\User');

        $ticket = new Ticket;
        $ticket->timestamps = false;
        $ticket->fill([
            'title' => $record['title'] ?? 'Imported ticket',
            'status' => TicketStatus::tryFrom($record['status'] ?? 'open') ?? TicketStatus::Open,
            'priority' => TicketPriority::tryFrom($record['priority'] ?? 'medium') ?? TicketPriority::Medium,
            'assigned_to' => $assigneeId,
            'department_id' => $departmentId,
            'metadata' => $record['metadata'] ?? null,
            'created_at' => $record['created_at'] ?? now(),
            'updated_at' => $record['updated_at'] ?? now(),
        ]);

        if ($requesterId) {
            $ticket->requester_type = $userModel;
            $ticket->requester_id = $requesterId;
        }

        $ticket->save();

        // Attach tags
        if (! empty($record['tag_source_ids'])) {
            $tagIds = array_filter(array_map(
                fn ($sid) => ImportSourceMap::resolve($job->id, 'tags', $sid),
                $record['tag_source_ids'],
            ));
            $ticket->tags()->sync($tagIds);
        }

        return $ticket->getKey();
    }

    private function persistReply(ImportJob $job, array $record, array $mappings): string|int
    {
        $ticketId = ImportSourceMap::resolve($job->id, 'tickets', $record['ticket_source_id'] ?? '');

        if (! $ticketId) {
            throw new \RuntimeException('Parent ticket not found for reply.');
        }

        $authorId = null;
        $authorType = null;
        $userModel = config('escalated.user_model', 'App\\Models\\User');

        if (! empty($record['author_source_id'])) {
            $authorId = ImportSourceMap::resolve($job->id, 'agents', $record['author_source_id'])
                ?? ImportSourceMap::resolve($job->id, 'contacts', $record['author_source_id']);
            $authorType = $userModel;
        }

        $reply = new Reply;
        $reply->timestamps = false;
        $reply->fill([
            'ticket_id' => $ticketId,
            'body' => $record['body'] ?? '',
            'is_internal_note' => $record['is_internal_note'] ?? false,
            'author_type' => $authorType,
            'author_id' => $authorId,
            'created_at' => $record['created_at'] ?? now(),
            'updated_at' => $record['updated_at'] ?? now(),
        ]);
        $reply->save();

        return $reply->getKey();
    }

    private function persistAttachment(ImportJob $job, array $record, array $mappings): string|int
    {
        $parentType = $record['parent_type'] ?? 'reply';
        $parentSourceId = $record['parent_source_id'] ?? '';

        $parentId = ImportSourceMap::resolve(
            $job->id,
            $parentType === 'ticket' ? 'tickets' : 'replies',
            $parentSourceId,
        );

        if (! $parentId) {
            throw new \RuntimeException("Parent {$parentType} not found for attachment.");
        }

        $parentModel = $parentType === 'ticket'
            ? Ticket::class
            : Reply::class;

        $attachment = Attachment::create([
            'attachable_type' => $parentModel,
            'attachable_id' => $parentId,
            'filename' => $record['filename'] ?? 'unknown',
            'mime_type' => $record['mime_type'] ?? 'application/octet-stream',
            'size' => $record['size'] ?? 0,
            'path' => $record['path'] ?? '',
            'disk' => config('escalated.attachments.disk', 'local'),
        ]);

        return $attachment->getKey();
    }

    private function persistCustomField(array $record, array $mappings): string|int
    {
        $field = CustomField::firstOrCreate(
            ['slug' => \Str::slug($record['name'])],
            [
                'name' => $record['name'],
                'type' => $record['type'] ?? 'text',
                'options' => $record['options'] ?? [],
            ],
        );

        return $field->getKey();
    }

    private function persistSatisfactionRating(ImportJob $job, array $record, array $mappings): string|int
    {
        $ticketId = ImportSourceMap::resolve($job->id, 'tickets', $record['ticket_source_id'] ?? '');

        if (! $ticketId) {
            throw new \RuntimeException('Ticket not found for satisfaction rating.');
        }

        $rating = SatisfactionRating::create([
            'ticket_id' => $ticketId,
            'rating' => $record['rating'] ?? $record['score'] ?? null,
            'comment' => $record['comment'] ?? null,
            'created_at' => $record['created_at'] ?? now(),
        ]);

        return $rating->getKey();
    }
}
