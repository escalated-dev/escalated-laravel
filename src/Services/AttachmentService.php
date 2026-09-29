<?php

namespace Escalated\Laravel\Services;

use Escalated\Laravel\Models\Attachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class AttachmentService
{
    public function store(Model $attachable, UploadedFile $file): Attachment
    {
        $disk = config('escalated.storage.disk', 'local');
        $basePath = config('escalated.storage.path', 'escalated/attachments');
        $extension = $file->guessExtension() ?? 'bin';
        $filename = Str::uuid().'.'.$extension;
        $path = $file->storeAs($basePath, $filename, ['disk' => $disk, 'visibility' => 'private']);
        if ($path === false) {
            throw new \RuntimeException('The attachment could not be stored.');
        }

        return $this->record($attachable, $disk, $path, [
            'filename' => $filename,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);
    }

    public function storeContent(Model $attachable, string $content, string $originalFilename, string $mimeType): Attachment
    {
        $disk = config('escalated.storage.disk', 'local');
        $filename = Str::uuid().'.'.(pathinfo($originalFilename, PATHINFO_EXTENSION) ?: 'bin');
        $path = trim(config('escalated.storage.path', 'escalated/attachments'), '/').'/'.$filename;
        if (! Storage::disk($disk)->put($path, $content, ['visibility' => 'private'])) {
            throw new \RuntimeException('The attachment could not be stored.');
        }

        return $this->record($attachable, $disk, $path, [
            'filename' => $filename,
            'original_filename' => $originalFilename,
            'mime_type' => $mimeType,
            'size' => strlen($content),
        ]);
    }

    private function record(Model $attachable, string $disk, string $path, array $attributes): Attachment
    {
        try {
            return Attachment::create($attributes + [
                'attachable_type' => $attachable->getMorphClass(),
                'attachable_id' => $attachable->getKey(),
                'disk' => $disk,
                'path' => $path,
            ]);
        } catch (Throwable $error) {
            Storage::disk($disk)->delete($path);
            throw $error;
        }
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
