<?php

namespace Escalated\Laravel\Http\Controllers\Admin;

use Escalated\Laravel\Contracts\EscalatedUiRenderer;
use Escalated\Laravel\Models\TwoFactor;
use Escalated\Laravel\Services\TwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class TwoFactorController extends Controller
{
    public function __construct(
        protected EscalatedUiRenderer $renderer,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $twoFactor = TwoFactor::where('user_id', $user->getKey())->first();

        return $this->renderer->render('Escalated/Admin/Settings/TwoFactor', [
            'enabled' => $twoFactor?->isConfirmed() ?? false,
            'pending' => $twoFactor && ! $twoFactor->isConfirmed(),
        ]);
    }

    public function setup(Request $request, TwoFactorService $service)
    {
        $user = $request->user();

        // Remove any existing unconfirmed setup
        TwoFactor::where('user_id', $user->getKey())
            ->whereNull('confirmed_at')
            ->delete();

        $secret = $service->generateSecret();
        $recoveryCodes = $service->generateRecoveryCodes();

        TwoFactor::create([
            'user_id' => $user->getKey(),
            'secret' => $secret,
            'recovery_codes' => $recoveryCodes,
        ]);

        return back()->with('two_factor_setup', [
            'qr_uri' => $service->generateQrUri($secret, $user->email),
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    public function confirm(Request $request, TwoFactorService $service)
    {
        $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $user = $request->user();
        $twoFactor = TwoFactor::where('user_id', $user->getKey())
            ->whereNull('confirmed_at')
            ->first();

        if (! $twoFactor) {
            return back()->withErrors(['code' => 'No pending two-factor setup found.']);
        }

        if (! $service->verify($twoFactor->secret, $request->code)) {
            return back()->withErrors(['code' => 'Invalid verification code.']);
        }

        $twoFactor->update(['confirmed_at' => now()]);

        return back()->with('success', 'Two-factor authentication enabled.');
    }

    public function disable(Request $request)
    {
        $user = $request->user();

        TwoFactor::where('user_id', $user->getKey())->delete();

        return back()->with('success', 'Two-factor authentication disabled.');
    }
}
