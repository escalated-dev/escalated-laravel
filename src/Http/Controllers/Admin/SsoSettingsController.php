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
            'sso_provider' => ['required', 'string', 'in:none,saml,jwt'],
            'sso_entity_id' => ['nullable', 'string', 'max:500'],
            'sso_url' => ['nullable', 'url', 'max:500'],
            'sso_certificate' => ['nullable', 'string', 'max:5000'],
            'sso_attr_email' => ['nullable', 'string', 'max:100'],
            'sso_attr_name' => ['nullable', 'string', 'max:100'],
            'sso_attr_role' => ['nullable', 'string', 'max:100'],
            'sso_jwt_secret' => ['nullable', 'string', 'max:500'],
            'sso_jwt_algorithm' => ['nullable', 'string', 'in:HS256,HS384,HS512,RS256'],
        ]);

        $sso->saveConfig($validated);

        return redirect()->back()->with('success', 'SSO settings updated.');
    }
}
