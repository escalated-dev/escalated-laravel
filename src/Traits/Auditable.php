<?php

namespace Escalated\Laravel\Traits;

use Escalated\Laravel\Models\AuditLog;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            static::logAudit($model, 'created', [], $model->getAttributes());
        });

        static::updated(function ($model) {
            $oldValues = [];
            $newValues = [];

            foreach ($model->getDirty() as $key => $value) {
                if (in_array($key, $model->getAuditExclude())) {
                    continue;
                }
                $oldValues[$key] = $model->getOriginal($key);
                $newValues[$key] = $value;
            }

            if (! empty($newValues)) {
                static::logAudit($model, 'updated', $oldValues, $newValues);
            }
        });

        static::deleted(function ($model) {
            static::logAudit($model, 'deleted', $model->getAttributes(), []);
        });
    }

    protected static function logAudit($model, string $action, array $oldValues, array $newValues): void
    {
        $request = request();

        AuditLog::create([
            'user_id' => $request?->user()?->id,
            'action' => $action,
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => $model->getKey(),
            'old_values' => ! empty($oldValues) ? $oldValues : null,
            'new_values' => ! empty($newValues) ? $newValues : null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }

    /**
     * Get attributes that should be excluded from audit logging.
     */
    public function getAuditExclude(): array
    {
        return $this->auditExclude ?? ['updated_at', 'created_at'];
    }
}
