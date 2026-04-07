<?php

namespace Escalated\Laravel\Http\Controllers;

use Escalated\Laravel\Mail\Adapters\InboundAdapter;
use Escalated\Laravel\Mail\Adapters\MailgunAdapter;
use Escalated\Laravel\Mail\Adapters\PostmarkAdapter;
use Escalated\Laravel\Mail\Adapters\SesAdapter;
use Escalated\Laravel\Models\EscalatedSettings;
use Escalated\Laravel\Services\InboundEmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class InboundEmailController extends Controller
{
    public function __construct(
        protected InboundEmailService $service,
    ) {}

    /**
     * Handle an inbound email webhook from an external adapter.
     */
    public function webhook(Request $request, string $adapter): JsonResponse
    {
        // Verify inbound email is enabled
        if (! EscalatedSettings::getBool('inbound_email_enabled', (bool) config('escalated.inbound_email.enabled', false))) {
            return response()->json(['error' => __('escalated::messages.inbound_email.disabled')], 404);
        }

        // Resolve the adapter
        $adapterInstance = $this->resolveAdapter($adapter);

        if (! $adapterInstance) {
            return response()->json(['error' => __('escalated::messages.inbound_email.unknown_adapter')], 400);
        }

        // Verify the request authenticity
        if (! $adapterInstance->verifyRequest($request)) {
            Log::warning('Escalated: Inbound email webhook verification failed.', [
                'adapter' => $adapter,
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => __('escalated::messages.inbound_email.invalid_signature')], 403);
        }

        try {
            $message = $adapterInstance->parseRequest($request);
            $inboundEmail = $this->service->process($message, $adapter);

            return response()->json([
                'status' => 'ok',
                'id' => $inboundEmail->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Escalated: Inbound email webhook processing failed.', [
                'adapter' => $adapter,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => __('escalated::messages.inbound_email.processing_failed')], 500);
        }
    }

    /**
     * Resolve the adapter instance by name.
     */
    protected function resolveAdapter(string $adapter): ?InboundAdapter
    {
        return match ($adapter) {
            'mailgun' => new MailgunAdapter,
            'postmark' => new PostmarkAdapter,
            'ses' => new SesAdapter,
            default => null,
        };
    }
}
