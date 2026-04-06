<?php

namespace Escalated\Laravel\Services;

use Escalated\Laravel\Models\Attachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AttachmentService
{
    public function store(Model $attachable, UploadedFile $file): Attachment
    {
        $disk = config('escalated.storage.disk', 'public');
        $basePath = config('escalated.storage.path', 'escalated/attachments');
        $extension = $file->guessExtension() ?? 'bin';
        $filename = Str::uuid().'.'.$extension;
        $path = $file->storeAs($basePath, $filename, $disk);

        return Attachment::create([
            'attachable_type' => $attachable->getMorphClass(),
            'attachable_id' => $attachable->getKey(),
            'filename' => $filename,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'disk' => $disk,
            'path' => $path,
        ]);
    }

    public function storeMany(Model $attachable, array $files): array
    {
        $attachments = [];

        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $attachments[] = $this->store($attachable, $file);
            }
        }

        return $attachments;
    }

    public function delete(Attachment $attachment): bool
    {
        Storage::disk($attachment->disk)->delete($attachment->path);

        return $attachment->delete();
    }
}
