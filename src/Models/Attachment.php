<?php

namespace Escalated\Laravel\Models;

use Escalated\Laravel\Concerns\UsesEscalatedConnection;
use Escalated\Laravel\Escalated;
use Escalated\Laravel\Services\AttachmentAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Attachment extends Model
{
    use UsesEscalatedConnection;

    protected $guarded = ['id'];

    protected $appends = ['url'];

    protected $hidden = ['disk', 'path'];

    public function getTable(): string
    {
        return Escalated::table('attachments');
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function getUrlAttribute(): string
    {
        return app(AttachmentAccess::class)->url($this);
    }

    public function url(): string
    {
        return $this->url;
    }

    public function sizeForHumans(): string
    {
        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2).' '.$units[$i];
    }
}
