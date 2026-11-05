<?php

namespace Escalated\Laravel\Http\Controllers\Agent;

use Escalated\Laravel\Enums\ChatStatus;
use Escalated\Laravel\Support\AgentStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

/**
 * Any agent, light agents included, sets their own status here. The admin
 * live-chat route (escalated.admin.chat.status) writes the same field.
 */
class StatusController extends Controller
{
    public function __invoke(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(AgentStatus::values())],
        ]);

        AgentStatus::set($request->user(), ChatStatus::from($validated['status']));

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['agent_status' => $validated['status']]);
        }

        return back();
    }
}
