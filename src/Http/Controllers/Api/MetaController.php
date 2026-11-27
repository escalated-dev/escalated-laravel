<?php

namespace Escalated\Laravel\Http\Controllers\Api;

use Composer\InstalledVersions;
use Escalated\Laravel\Models\ApiToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;

class MetaController extends Controller
{
    public const API_VERSION = '1.0';

    public function __invoke(Request $request): JsonResponse
    {
        $body = [
            'api_version' => self::API_VERSION,
            'backend' => 'laravel',
        ];

        if ($this->hasValidToken($request)) {
            $body['backend_version'] = $this->backendVersion();
        }

        $body['features'] = $this->features();
        $body['connect_url'] = Route::has('escalated.agent.extension.connect')
            ? route('escalated.agent.extension.connect')
            : null;
        $body['web_url_template'] = $this->webUrlTemplate();

        return response()->json($body)->withHeaders([
            'Vary' => 'Authorization',
            'Cache-Control' => 'no-store',
        ]);
    }

    /** @return list<string> */
    protected function features(): array
    {
        $features = [
            'tickets.create',
            'tickets.macro',
            'tickets.updated_since',
            'tickets.last_customer_reply_at',
            'canned_responses',
            'macros',
            'auth.exchange',
            'auth.revoke',
        ];

        if (config('escalated.broadcasting.enabled', false)) {
            $features[] = 'realtime';
        }

        return $features;
    }

    /** Absolute agent ticket URL with a literal {reference} placeholder. */
    protected function webUrlTemplate(): ?string
    {
        $route = Route::getRoutes()->getByName('escalated.agent.tickets.show');

        return $route
            ? url(str_replace('{ticket}', '{reference}', $route->uri()))
            : null;
    }

    /** An unknown, expired or ownerless token is ignored, never a 401. */
    protected function hasValidToken(Request $request): bool
    {
        $header = (string) $request->header('Authorization', '');
        if (! str_starts_with($header, 'Bearer ') || strlen($header) <= 7) {
            return false;
        }

        $token = ApiToken::findByPlainText(substr($header, 7));

        return $token !== null && ! $token->isExpired() && $token->tokenable !== null;
    }

    protected function backendVersion(): string
    {
        $version = InstalledVersions::getPrettyVersion('escalated-dev/escalated-laravel');

        return ltrim($version ?: 'dev', 'v');
    }
}
