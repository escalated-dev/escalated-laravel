<?php

namespace Escalated\Laravel\Http\Middleware;

use Closure;
use Escalated\Laravel\Support\StaffAccess;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The Ticketable check without a staff gate. Takes EnsureIsAdmin's place on
 * the embedded admin routes when the host's middleware replaces the admin
 * gate (escalated.ui.embed.replace_admin_gate), so staff actions there fail
 * with UserNotTicketableException like everywhere else.
 */
class EnsureTicketableActor
{
    public function handle(Request $request, Closure $next): Response
    {
        StaffAccess::ensureCanAct($request, $request->user());

        return $next($request);
    }
}
