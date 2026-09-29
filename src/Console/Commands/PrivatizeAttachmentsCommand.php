<?php

namespace Escalated\Laravel\Console\Commands;

use Escalated\Laravel\Models\Attachment;
use Escalated\Laravel\Models\AttachmentMigration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class PrivatizeAttachmentsCommand extends Command
{
    protected $signature = 'escalated:attachments:privatize
        {--from=public : Source disk containing existing public attachments}
        {--to= : Private destination disk (defaults to escalated.storage.disk)}
        {--apply : Copy, verify, update records and remove unreferenced public originals}';

    protected $description = 'Move public attachments to private storage; dry run unless --apply is supplied';

    public function handle(): int
    {
        $from = (string) $this->option('from');
        $to = (string) ($this->option('to') ?: config('escalated.storage.disk', 'local'));
        if ($from === $to || $to === 'public' || config("filesystems.disks.$to.visibility") === 'public') {
            $this->error('Choose a distinct private destination disk.');

            return self::FAILURE;
        }
        if (AttachmentMigration::where('source_disk', $from)->where('destination_disk', '!=', $to)->exists()) {
            $this->error('Outstanding recovery entries use another destination. Finish recovery using that destination first.');

            return self::FAILURE;
        }

        if (! $this->option('apply')) {
            $this->info(Attachment::where('disk', $from)->count().' attachment records would be copied and verified. No files or records changed.');
            $this->info($this->journal($from, $to)->count().' recovery entries await reconciliation.');

            return self::SUCCESS;
        }

        $failures = 0;
        $this->recover($from, $to, $failures);
        Attachment::where('disk', $from)->chunkById(100, function ($attachments) use ($from, $to, &$failures) {
            foreach ($attachments as $attachment) {
                try {
                    $this->move($attachment, $from, $to);
                    $this->info('Privatized attachment '.$attachment->getKey().'.');
                } catch (Throwable $error) {
                    $failures++;
                    $this->error('Attachment '.$attachment->getKey().': '.$error->getMessage());
                }
            }
        });

        $this->recover($from, $to, $failures);
        if ($this->journal($from, $to)->exists()) {
            $this->error('Public-file cleanup is incomplete. Resolve the reported errors and rerun with the same source and destination.');
            $failures++;
        }

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function move(Attachment $attachment, string $from, string $to): void
    {
        $source = Storage::disk($from);
        $destination = Storage::disk($to);
        $oldPath = $attachment->path;
        $newPath = trim(config('escalated.storage.path', 'escalated/attachments'), '/').'/'.Str::uuid();
        // Persist before writing bytes. A process termination at any subsequent
        // step leaves enough information for a rerun to reconcile both disks.
        $move = AttachmentMigration::create([
            'attachment_id' => $attachment->getKey(),
            'source_disk' => $from, 'source_path' => $oldPath,
            'destination_disk' => $to, 'destination_path' => $newPath,
        ]);
        try {
            $stream = $source->readStream($oldPath);
            if (! is_resource($stream)) {
                throw new RuntimeException('The source file could not be read; nothing changed.');
            }
            try {
                $copied = $destination->put($newPath, $stream, ['visibility' => 'private']);
            } finally {
                fclose($stream);
            }
            if (! $copied) {
                throw new RuntimeException('The private copy failed; the public original was retained.');
            }

            if (! hash_equals($this->checksum($from, $oldPath), $this->checksum($to, $newPath))) {
                throw new RuntimeException('Checksum verification failed; the public original was retained.');
            }

            $attachment->getConnection()->transaction(function () use ($attachment, $from, $to, $oldPath, $newPath, $move) {
                $updated = Attachment::whereKey($attachment->getKey())->where('disk', $from)->where('path', $oldPath)
                    ->update(['disk' => $to, 'path' => $newPath]);
                if ($updated !== 1) {
                    throw new RuntimeException('The record changed during migration; the original was retained.');
                }
                $move->update(['status' => 'moved']);
            });
        } catch (Throwable $error) {
            // Atomic record+journal updates make pending copies safe to discard.
            // If cleanup itself fails the durable entry remains for the next run.
            $this->discardPending($move->fresh());
            throw $error;
        }
    }

    private function journal(string $from, string $to)
    {
        return AttachmentMigration::where('source_disk', $from)->where('destination_disk', $to);
    }

    private function recover(string $from, string $to, int &$failures): void
    {
        $this->journal($from, $to)->chunkById(100, function ($moves) use (&$failures) {
            foreach ($moves as $move) {
                try {
                    if ($move->status === 'pending') {
                        $this->discardPending($move);
                    } elseif ($move->status === 'moved') {
                        // Several records may share one public source file.
                        if (Attachment::where('disk', $move->source_disk)->where('path', $move->source_path)->exists()) {
                            continue;
                        }
                        $source = Storage::disk($move->source_disk);
                        if ($source->exists($move->source_path) && ! $source->delete($move->source_path)) {
                            throw new RuntimeException('Could not remove public original '.$move->source_disk.':'.$move->source_path);
                        }
                        $move->delete();
                    } else {
                        throw new RuntimeException('Unknown recovery state; inspect the journal before continuing.');
                    }
                } catch (Throwable $error) {
                    $failures++;
                    $this->error('Recovery entry '.$move->id.': '.$error->getMessage());
                }
            }
        });
    }

    private function discardPending(AttachmentMigration $move): void
    {
        if ($move->status !== 'pending' || Attachment::where('disk', $move->destination_disk)->where('path', $move->destination_path)->exists()) {
            throw new RuntimeException('The recovery entry changed; its files were retained for inspection.');
        }
        if (! Attachment::whereKey($move->attachment_id)->where('disk', $move->source_disk)->where('path', $move->source_path)->exists()) {
            $move->update(['status' => 'conflict']);
            throw new RuntimeException('The source record changed. Both files and the recovery entry were retained for manual reconciliation.');
        }
        $destination = Storage::disk($move->destination_disk);
        if ($destination->exists($move->destination_path) && ! $destination->delete($move->destination_path)) {
            throw new RuntimeException('Could not remove an unused private copy; its recovery entry was retained.');
        }
        $move->delete();
    }

    private function checksum(string $disk, string $path): string
    {
        $stream = Storage::disk($disk)->readStream($path);
        if (! is_resource($stream)) {
            throw new RuntimeException('A verification stream could not be read; the public original was retained.');
        }
        try {
            $hash = hash_init('sha256');
            hash_update_stream($hash, $stream);

            return hash_final($hash);
        } finally {
            fclose($stream);
        }
    }
}
