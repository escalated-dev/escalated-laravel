<?php

namespace Escalated\Laravel\Models;

use Escalated\Laravel\Escalated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class Attachment extends Model
{
    protected $guarded = ['id'];

    protected $appends = ['url'];

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
        return Storage::disk($this->disk)->url($this->path);
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
