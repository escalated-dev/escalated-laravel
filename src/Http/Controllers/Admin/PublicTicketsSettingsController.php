<?php

namespace Escalated\Laravel\Http\Controllers\Admin;

use Escalated\Laravel\Contracts\EscalatedUiRenderer;
use Escalated\Laravel\Models\EscalatedSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Admin settings page for the public-ticket guest policy.
 *
 * Controls the identity assigned to tickets submitted via the public widget or
 * inbound email: either "unassigned" (no requester; Contact row still carries
 * the guest email), "guest_user" (every public ticket owned by a pre-created
 * shared host-app user), or "prompt_signup" (outbound confirmation email
 * embeds a signup invite link).
 *
 * The settings store is read at request time by the widget controller, so
 * admins can switch modes at runtime without a redeploy.
 */
class PublicTicketsSettingsController extends Controller
{
    public function __construct(
        protected EscalatedUiRenderer $renderer,
    ) {}

    public function index(): mixed
    {
        return $this->renderer->render('Escalated/Admin/Settings/PublicTickets', [
            'settings' => $this->getSettings(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'guest_policy_mode' => ['required', 'string', 'in:unassigned,guest_user,prompt_signup'],
            'guest_policy_user_id' => ['nullable', 'integer', 'min:1', 'required_if:guest_policy_mode,guest_user'],
            'guest_policy_signup_url_template' => ['nullable', 'string', 'max:500'],
        ]);

        EscalatedSettings::set('guest_policy_mode', $validated['guest_policy_mode']);
        EscalatedSettings::set(
            'guest_policy_user_id',
            $validated['guest_policy_mode'] === 'guest_user'
                ? (string) $validated['guest_policy_user_id']
                : ''
        );
        EscalatedSettings::set(
            'guest_policy_signup_url_template',
            $validated['guest_policy_mode'] === 'prompt_signup'
                ? (string) ($validated['guest_policy_signup_url_template'] ?? '')
                : ''
        );

        return redirect()->back()->with('success', 'Guest policy updated.');
    }

    protected function getSettings(): array
    {
        $userIdRaw = EscalatedSettings::get('guest_policy_user_id', '');

        return [
            'guest_policy_mode' => EscalatedSettings::get('guest_policy_mode', 'unassigned'),
            'guest_policy_user_id' => $userIdRaw === '' ? null : (int) $userIdRaw,
            'guest_policy_signup_url_template' => EscalatedSettings::get(
                'guest_policy_signup_url_template',
                ''
            ),
        ];
    }
}
