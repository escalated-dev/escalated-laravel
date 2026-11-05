<?php

namespace Escalated\Laravel\Http\Middleware;

use Closure;
use Escalated\Laravel\Services\CustomerPresenceTracker;
use Escalated\Laravel\Support\StaffAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Customer-portal requests record that the signed-in customer is on the site
 * (throttled). Staff browsing the portal are not customers and are not
 * recorded.
 */
class RecordCustomerPresence
{
    public function __construct(private CustomerPresenceTracker $presence) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();
        if ($user instanceof Model && ! StaffAccess::isStaff($user)) {
            try {
                $this->presence->seeUser($user);
            } catch (\Throwable) {
                // Presence is a hint; it never breaks the customer's page.
            }
        }

        return $response;
    }
}
