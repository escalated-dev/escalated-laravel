<?php

namespace Escalated\Laravel\Http\Controllers\Admin;

use Escalated\Laravel\Contracts\EscalatedUiRenderer;
use Escalated\Laravel\Escalated;
use Escalated\Laravel\Models\ApiToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class ApiTokenController extends Controller
{
    public function __construct(
        protected EscalatedUiRenderer $renderer,
    ) {}

    public function index(): mixed
    {
        $tokens = ApiToken::with('tokenable')->latest()->get()->map(fn ($token) => [
            'id' => $token->id,
            'name' => $token->name,
            'user_name' => $token->tokenable?->name,
            'user_email' => $token->tokenable?->email,
            'abilities' => $token->abilities,
            'last_used_at' => $token->last_used_at?->toIso8601String(),
            'last_used_ip' => $token->last_used_ip,
            'expires_at' => $token->expires_at?->toIso8601String(),
            'is_expired' => $token->isExpired(),
            'created_at' => $token->created_at->toIso8601String(),
        ]);

        $userModel = Escalated::newUserModel();
        $agentGate = config('escalated.authorization.agent_gate', 'escalated-agent');

        $query = $userModel->newQuery();
        $agentScope = config('escalated.authorization.agent_scope');
        if ($agentScope && is_callable($agentScope)) {
            $agentUsers = $agentScope($query)->get();
        } else {
            $agentUsers = $query->limit(500)->get()->filter(function ($user) use ($agentGate) {
                return Gate::forUser($user)->allows($agentGate);
            });
        }

        $users = $agentUsers->map(fn ($u) => ['id' => $u->getKey(), 'name' => $u->name, 'email' => $u->email])->values();

        return $this->renderer->render('Escalated/Admin/ApiTokens/Index', [
            'tokens' => $tokens,
            'users' => $users,
            'api_enabled' => config('escalated.api.enabled', false),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'user_id' => 'required|integer',
            'abilities' => 'required|array',
            'abilities.*' => 'string|in:agent,admin,*',
            'expires_in_days' => 'nullable|integer|min:1|max:365',
        ]);

        $userModel = Escalated::newUserModel();
        $user = $userModel->newQuery()->findOrFail($validated['user_id']);

        $expiresAt = ! empty($validated['expires_in_days'])
            ? now()->addDays($validated['expires_in_days'])
            : null;

        $result = ApiToken::createToken($user, $validated['name'], $validated['abilities'], $expiresAt);

        return back()->with([
            'success' => 'API token created.',
            'plain_text_token' => $result['plainTextToken'],
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $token = ApiToken::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'abilities' => 'sometimes|array',
            'abilities.*' => 'string|in:agent,admin,*',
        ]);

        $token->update($validated);

        return back()->with('success', 'Token updated.');
    }

    public function destroy(int $id): RedirectResponse
    {
        ApiToken::findOrFail($id)->delete();

        return back()->with('success', 'Token revoked.');
    }
}
