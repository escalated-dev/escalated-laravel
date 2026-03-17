<?php

namespace Escalated\Laravel\Models;

use Escalated\Laravel\Escalated;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportJob extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected static array $validTransitions = [
        'pending' => ['authenticating'],
        'authenticating' => ['mapping', 'failed'],
        'mapping' => ['importing', 'failed'],
        'importing' => ['paused', 'completed', 'failed'],
        'paused' => ['importing', 'failed'],
        'completed' => [],
        'failed' => ['mapping'],
    ];

    public function getTable(): string
    {
        return Escalated::table('import_jobs');
    }

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'field_mappings' => 'array',
            'progress' => 'array',
            'error_log' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function sourceMaps(): HasMany
    {
        return $this->hasMany(ImportSourceMap::class, 'import_job_id');
    }

    public function transitionTo(string $newStatus): void
    {
        $allowed = static::$validTransitions[$this->status ?? 'pending'] ?? [];

        if (! in_array($newStatus, $allowed)) {
            throw new \InvalidArgumentException(
                "Cannot transition from '{$this->status}' to '{$newStatus}'."
            );
        }

        $this->update(['status' => $newStatus]);
    }

    public function updateEntityProgress(
        string $entityType,
        ?int $processed = null,
        ?int $total = null,
        ?int $skipped = null,
        ?int $failed = null,
        ?string $cursor = null,
    ): void {
        $progress = $this->progress ?? [];
        $current = $progress[$entityType] ?? [
            'total' => 0, 'processed' => 0, 'skipped' => 0, 'failed' => 0, 'cursor' => null,
        ];

        if ($processed !== null) $current['processed'] = $processed;
        if ($total !== null) $current['total'] = $total;
        if ($skipped !== null) $current['skipped'] = $skipped;
        if ($failed !== null) $current['failed'] = $failed;
        if ($cursor !== null) $current['cursor'] = $cursor;

        $progress[$entityType] = $current;
        $this->update(['progress' => $progress]);
    }

    public function getEntityCursor(string $entityType): ?string
    {
        return $this->progress[$entityType]['cursor'] ?? null;
    }

    public function appendError(string $entityType, string $sourceId, string $error): void
    {
        $log = $this->error_log ?? [];

        if (count($log) < 10000) {
            $log[] = [
                'entity_type' => $entityType,
                'source_id' => $sourceId,
                'error' => $error,
                'timestamp' => now()->toIso8601String(),
            ];
            $this->update(['error_log' => $log]);
        }
    }

    public function purgeCredentials(): void
    {
        $this->update(['credentials' => null]);
    }

    public function isResumable(): bool
    {
        return in_array($this->status, ['paused', 'failed']);
    }
}
