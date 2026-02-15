<?php

namespace Escalated\Laravel\Http\Middleware;

use Closure;
use Escalated\Laravel\Models\ApiToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next, ?string $ability = null): Response
    {
        $token = $this->extractToken($request);

        if (! $token) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $apiToken = ApiToken::findByPlainText($token);

        if (! $apiToken) {
            return response()->json(['message' => 'Invalid token.'], 401);
        }

        if ($apiToken->isExpired()) {
            return response()->json(['message' => 'Token has expired.'], 401);
        }

        if ($ability && ! $apiToken->hasAbility($ability)) {
            return response()->json(['message' => 'Insufficient permissions.'], 403);
        }

        $user = $apiToken->tokenable;

        if (! $user) {
            return response()->json(['message' => 'Token owner not found.'], 401);
        }

        // Verify the user still has the required gate permission
        $agentGate = config('escalated.authorization.agent_gate', 'escalated-agent');
        if (! Gate::forUser($user)->allows($agentGate)) {
            return response()->json(['message' => 'User no longer has agent access.'], 403);
        }

        if ($ability === 'admin') {
            $adminGate = config('escalated.authorization.admin_gate', 'escalated-admin');
            if (! Gate::forUser($user)->allows($adminGate)) {
                return response()->json(['message' => 'Insufficient permissions.'], 403);
            }
        }

        // Throttle last_used_at updates to avoid a DB write on every request
        if (! $apiToken->last_used_at || $apiToken->last_used_at->diffInMinutes(now()) >= 5) {
            $apiToken->update([
                'last_used_at' => now(),
                'last_used_ip' => $request->ip(),
            ]);
        }

        $request->headers->set('Accept', 'application/json');
        $request->setUserResolver(fn () => $user);
        $request->attributes->set('api_token', $apiToken);

        return $next($request);
    }

    protected function extractToken(Request $request): ?string
    {
        $header = $request->header('Authorization', '');

        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        return null;
    }
}
