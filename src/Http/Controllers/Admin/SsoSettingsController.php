<?php

namespace Escalated\Laravel\Http\Controllers\Admin;

use Escalated\Laravel\Contracts\EscalatedUiRenderer;
use Escalated\Laravel\Services\SsoService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SsoSettingsController extends Controller
{
    public function __construct(
        protected EscalatedUiRenderer $renderer,
    ) {}

    public function index(SsoService $sso): mixed
    {
        return $this->renderer->render('Escalated/Admin/Settings/SsoSettings', [
            'settings' => $sso->getConfig(),
        ]);
    }

    public function update(Request $request, SsoService $sso)
    {
        $validated = $request->validate([
            'sso_provider' => ['required', 'string', 'in:none,saml,jwt,oauth'],
            'sso_entity_id' => ['nullable', 'string', 'max:500'],
            'sso_url' => ['nullable', 'url', 'max:500'],
            'sso_login_url' => ['nullable', 'url', 'max:500'],
            'sso_logout_url' => ['nullable', 'url', 'max:500'],
            'sso_metadata_url' => ['nullable', 'url', 'max:500'],
            'sso_certificate' => ['nullable', 'string', 'max:10000'],
            'sso_attr_email' => ['nullable', 'string', 'max:100'],
            'sso_attr_name' => ['nullable', 'string', 'max:100'],
            'sso_attr_role' => ['nullable', 'string', 'max:100'],
            'sso_jwt_secret' => ['nullable', 'string', 'max:500'],
            'sso_jwt_algorithm' => ['nullable', 'string', 'in:HS256,HS384,HS512,RS256,RS384,RS512'],
            'sso_oauth_authorize_url' => ['nullable', 'url', 'max:500'],
            'sso_oauth_token_url' => ['nullable', 'url', 'max:500'],
            'sso_oauth_userinfo_url' => ['nullable', 'url', 'max:500'],
            'sso_oauth_client_id' => ['nullable', 'string', 'max:255'],
            'sso_oauth_client_secret' => ['nullable', 'string', 'max:500'],
            'sso_oauth_scopes' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validated['sso_provider'] === 'saml') {
            $request->validate([
                'sso_entity_id' => ['required', 'string', 'max:500'],
                'sso_login_url' => ['required', 'url', 'max:500'],
            ]);
        }

        if ($validated['sso_provider'] === 'jwt') {
            $request->validate([
                'sso_url' => ['required', 'url', 'max:500'],
                'sso_jwt_secret' => ['required', 'string', 'max:500'],
            ]);
        }

        if ($validated['sso_provider'] === 'oauth') {
            $request->validate([
                'sso_oauth_authorize_url' => ['required', 'url', 'max:500'],
                'sso_oauth_token_url' => ['required', 'url', 'max:500'],
                'sso_oauth_userinfo_url' => ['required', 'url', 'max:500'],
                'sso_oauth_client_id' => ['required', 'string', 'max:255'],
            ]);
        }

        $sso->saveConfig($validated);

        return redirect()->back()->with('success', 'SSO settings updated.');
    }
}
