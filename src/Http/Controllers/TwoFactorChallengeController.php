<?php

namespace Escalated\Laravel\Http\Controllers;

use Escalated\Laravel\Contracts\EscalatedUiRenderer;
use Escalated\Laravel\Services\TwoFactorService;
use Escalated\Laravel\Support\StaffAccess;
use Escalated\Laravel\Support\TwoFactorSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

/**
 * The second step after the host's password login: renders the shared
 * Auth/TwoFactorChallenge page and checks a TOTP or recovery code.
 */
class TwoFactorChallengeController extends Controller
{
    public function __construct(
        protected EscalatedUiRenderer $renderer,
    ) {}

    public function show(Request $request): mixed
    {
        if (! TwoFactorService::confirmedFor($request->user())
            || TwoFactorSession::passed($request->session(), $request->user())) {
            return redirect()->intended($this->home($request));
        }

        return $this->renderer->render('Escalated/Auth/TwoFactorChallenge', [
            'action' => route('escalated.two-factor.verify'),
            'error' => (string) $request->session()->get('error', ''),
        ]);
    }

    public function verify(Request $request, TwoFactorService $service): RedirectResponse
    {
        $user = $request->user();
        $twoFactor = TwoFactorService::confirmedFor($user);

        if (! $twoFactor) {
            return redirect()->intended($this->home($request));
        }

        $validated = $request->validate([
            'code' => ['required_without:recovery_code', 'nullable', 'string', 'max:16'],
            'recovery_code' => ['required_without:code', 'nullable', 'string', 'max:64'],
        ]);

        $usingRecovery = filled($validated['recovery_code'] ?? null);
        $passed = $usingRecovery
            ? $service->useRecoveryCode($twoFactor, (string) $validated['recovery_code'])
            : $service->verifyAndConsume($twoFactor, (string) $validated['code']);

        if (! $passed) {
            Log::notice('Escalated: two-factor challenge failed', ['user_id' => $user->getKey(), 'ip' => $request->ip()]);

            return back()->withErrors($usingRecovery
                ? ['recovery_code' => 'That recovery code is not valid.']
                : ['code' => 'That code is not valid.']);
        }

        $request->session()->regenerate();
        TwoFactorSession::markPassed($request->session(), $user);

        return redirect()->intended($this->home($request));
    }

    protected function home(Request $request): string
    {
        return StaffAccess::isAgent($request->user())
            ? route('escalated.agent.dashboard')
            : route('escalated.admin.reports');
    }
}
