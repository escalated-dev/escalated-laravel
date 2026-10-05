<?php

namespace Escalated\Laravel\Support;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Per-client-IP limits for the unauthenticated guest routes (browser, widget
 * and mobile). Every accepted guest ticket or reply writes rows and sends
 * mail, so the package caps them itself. Over the limit Laravel's `throttle`
 * middleware answers 429 with `Retry-After`. Configured under
 * `escalated.guest_rate_limits`; counters live in the app's rate-limiter
 * cache store (`cache.limiter`, else the default cache store).
 *
 * The key is `$request->ip()`. Behind a load balancer or proxy the host must
 * configure trusted proxies, or every guest shares the proxy's address.
 */
class GuestRateLimits
{
    public static function register(): void
    {
        foreach (['requests' => 60, 'submissions' => 5, 'replies' => 30] as $purpose => $default) {
            RateLimiter::for('escalated-guest-'.$purpose, function (Request $request) use ($purpose, $default) {
                if (! config('escalated.guest_rate_limits.enabled', true)) {
                    return Limit::none();
                }

                $maximum = max(1, (int) config('escalated.guest_rate_limits.'.$purpose.'_per_minute', $default));

                // Share limits across web, mobile and widget entry points.
                // Changing an email, token, route or account must not reset an
                // anonymous caller's budget.
                return Limit::perMinute($maximum)->by(hash('sha256', (string) $request->ip()));
            });
        }
    }
}
