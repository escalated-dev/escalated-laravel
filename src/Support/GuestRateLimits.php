<?php

namespace Escalated\Laravel\Support;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class GuestRateLimits
{
    public static function register(): void
    {
        foreach (['requests' => 60, 'submissions' => 5, 'replies' => 30] as $purpose => $default) {
            RateLimiter::for('escalated-guest-'.$purpose, function (Request $request) use ($purpose, $default) {
                $maximum = max(1, (int) config('escalated.guest_rate_limits.'.$purpose.'_per_minute', $default));

                // Share limits across web, mobile and widget entry points.
                // Changing an email, token, route or account must not reset an
                // anonymous caller's budget. Hosts configure trusted proxies.
                return Limit::perMinute($maximum)->by(hash('sha256', (string) $request->ip()));
            });
        }
    }
}
