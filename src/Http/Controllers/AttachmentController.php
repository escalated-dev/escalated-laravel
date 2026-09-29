<?php

namespace Escalated\Laravel\Http\Controllers;

use Escalated\Laravel\Http\Middleware\AuthenticateApiToken;
use Escalated\Laravel\Models\Attachment;
use Escalated\Laravel\Services\AttachmentAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class AttachmentController
{
    public function __invoke(Request $request, string $attachment): Response
    {
        // Mobile/API clients can use their package token; browser downloads use
        // the host's session. An invalid supplied bearer token never falls back.
        if ($request->bearerToken()) {
            return app(AuthenticateApiToken::class)->handle($request, fn (Request $request) => $this->download($request, $attachment));
        }

        if ($guard = config('escalated.storage.download_guard')) {
            $request->setUserResolver(fn () => Auth::guard($guard)->user());
        }

        return $this->download($request, $attachment);
    }

    private function download(Request $request, string $id): Response
    {
        $attachment = Attachment::findOrFail($id);
        app(AttachmentAccess::class)->authorize($request, $attachment);
        $disk = Storage::disk($attachment->disk);
        abort_unless($disk->exists($attachment->path), 404);

        $filename = basename(str_replace('\\', '/', (string) $attachment->original_filename));
        $filename = preg_replace('/[\x00-\x1F\x7F]/', '', $filename) ?: 'attachment';

        return $disk->download($attachment->path, $filename, [
            'Cache-Control' => 'private, no-store, max-age=0',
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }
}
