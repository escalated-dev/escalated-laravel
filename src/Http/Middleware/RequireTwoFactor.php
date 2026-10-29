<?php

namespace Escalated\Laravel\Http\Middleware;

use Closure;
use Escalated\Laravel\Services\TwoFactorService;
use Escalated\Laravel\Support\AccessFeatures;
use Escalated\Laravel\Support\StaffAccess;
use Escalated\Laravel\Support\TwoFactorSession;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * While two-factor is switched on (escalated.access.two_factor):
 *
 * - a user with a confirmed enrolment who has not passed the challenge in
 *   this session is sent to it (JSON: 403 with two_factor_required);
 * - when two-factor is required, a staff user without an enrolment is sent to
 *   enrol and can reach nothing but the enrolment screen.
 *
 * Does nothing while the switch is off.
 */
class RequireTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! AccessFeatures::twoFactor() || ! $user || ! $request->hasSession()) {
            return $next($request);
        }

        if (TwoFactorService::confirmedFor($user)) {
            if (TwoFactorSession::passed($request->session(), $user)) {
                return $next($request);
            }

            return $this->deny($request, 'escalated.two-factor.challenge', 'Two-factor authentication is required.');
        }

        if (TwoFactorService::required()
            && StaffAccess::isStaff($user)
            && ! $request->routeIs('escalated.admin.two-factor.*')) {
            return $this->deny($request, 'escalated.admin.two-factor.index', 'Set up two-factor authentication to continue.');
        }

        return $next($request);
    }

    protected function deny(Request $request, string $route, string $message): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'two_factor_required' => true], 403);
        }

        if ($request->isMethod('GET')) {
            redirect()->setIntendedUrl($request->fullUrl());
        }

        return redirect()->route($route);
    }
}
