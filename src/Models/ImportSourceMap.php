<?php

namespace Escalated\Laravel\Models;

use Escalated\Laravel\Escalated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportSourceMap extends Model
{
    const UPDATED_AT = null;

    protected $guarded = ['id'];

    public function getTable(): string
    {
        return Escalated::table('import_source_maps');
    }

    public function importJob(): BelongsTo
    {
        return $this->belongsTo(ImportJob::class, 'import_job_id');
    }

    public static function resolve(string $jobId, string $entityType, string $sourceId): ?string
    {
        return static::where('import_job_id', $jobId)
            ->where('entity_type', $entityType)
            ->where('source_id', $sourceId)
            ->value('escalated_id');
    }

    public static function hasBeenImported(string $jobId, string $entityType, string $sourceId): bool
    {
        return static::resolve($jobId, $entityType, $sourceId) !== null;
    }
}
