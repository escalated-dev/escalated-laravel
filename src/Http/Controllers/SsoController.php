<?php

namespace Escalated\Laravel\Http\Controllers;

use Escalated\Laravel\Exceptions\SsoException;
use Escalated\Laravel\Services\SsoService;
use Escalated\Laravel\Services\SsoUserResolver;
use Escalated\Laravel\Support\StaffAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Sign-in through the SSO provider chosen in Admin > Settings > SSO.
 * Every route answers 404 unless escalated.access.sso is on (route
 * middleware) and the provider it serves is the chosen one.
 */
class SsoController extends Controller
{
    protected const OAUTH_STATE = 'escalated.sso.oauth_state';

    protected const OAUTH_PKCE = 'escalated.sso.oauth_pkce';

    public function __construct(
        protected SsoService $sso,
        protected SsoUserResolver $users,
    ) {}

    public function login(Request $request): RedirectResponse|Response
    {
        $provider = $this->sso->getProvider();
        abort_if($provider === 'none', 404);

        try {
            return match ($provider) {
                'saml' => redirect()->away($this->sso->samlLoginUrl()),
                'oauth' => $this->startOauth($request),
                'jwt' => $this->startJwt(),
            };
        } catch (SsoException $e) {
            return $this->refuse($e);
        }
    }

    public function metadata(): Response
    {
        $this->requireProvider('saml');

        try {
            return response($this->sso->samlMetadata(), 200, ['Content-Type' => 'application/samlmetadata+xml']);
        } catch (SsoException $e) {
            Log::warning('Escalated SSO: '.$e->getMessage());
            abort(500, 'SAML metadata is not available.');
        }
    }

    public function samlAcs(Request $request): RedirectResponse|Response
    {
        $this->requireProvider('saml');

        return $this->signIn($request, fn () => $this->sso->validateSamlResponse((string) $request->input('SAMLResponse', '')));
    }

    public function oauthCallback(Request $request): RedirectResponse|Response
    {
        $this->requireProvider('oauth');

        $state = (string) $request->session()->pull(self::OAUTH_STATE, '');
        $verifier = (string) $request->session()->pull(self::OAUTH_PKCE, '');

        return $this->signIn($request, function () use ($request, $state, $verifier) {
            if ($request->filled('error')) {
                throw new SsoException('OAuth refused: the provider returned '.$request->input('error').'.');
            }
            if ($state === '' || ! hash_equals($state, (string) $request->input('state', ''))) {
                throw new SsoException('OAuth refused: missing or mismatched state.');
            }
            if (! $request->filled('code') || $verifier === '') {
                throw new SsoException('OAuth refused: no authorization code or PKCE verifier.');
            }

            return $this->sso->oauthIdentity((string) $request->input('code'), $verifier);
        });
    }

    public function jwtCallback(Request $request): RedirectResponse|Response
    {
        $this->requireProvider('jwt');

        return $this->signIn($request, fn () => $this->sso->validateJwtToken((string) $request->input('jwt', '')));
    }

    protected function startOauth(Request $request): RedirectResponse
    {
        $provider = $this->sso->oauthProvider();
        $url = $provider->getAuthorizationUrl();

        $request->session()->put(self::OAUTH_STATE, $provider->getState());
        $request->session()->put(self::OAUTH_PKCE, $provider->getPkceCode());

        return redirect()->away($url);
    }

    protected function startJwt(): RedirectResponse
    {
        $config = $this->sso->getConfig();
        $url = trim((string) ($config['sso_login_url'] ?: $config['sso_url']));

        if ($url === '') {
            throw new SsoException('JWT sign-in has no login URL configured.');
        }

        $query = http_build_query(['return_to' => route('escalated.sso.jwt.callback')]);

        return redirect()->away($url.(str_contains($url, '?') ? '&' : '?').$query);
    }

    /** @param  callable(): array{email: string, name: string, role: string}  $identify */
    protected function signIn(Request $request, callable $identify): RedirectResponse|Response
    {
        try {
            $user = $this->users->resolve($identify());
        } catch (SsoException $e) {
            return $this->refuse($e);
        }

        Auth::guard(config('escalated.sso.guard'))->login($user);
        $request->session()->regenerate();

        Log::info('Escalated SSO: signed in', ['user_id' => $user->getKey(), 'provider' => $this->sso->getProvider()]);

        return redirect()->intended($this->home($user));
    }

    protected function home(mixed $user): string
    {
        if ($target = config('escalated.sso.redirect_to')) {
            return (string) $target;
        }

        if (StaffAccess::isAgent($user)) {
            return route('escalated.agent.dashboard');
        }

        return StaffAccess::isAdmin($user)
            ? route('escalated.admin.reports')
            : url(config('escalated.routes.prefix', 'support'));
    }

    protected function refuse(SsoException $e): Response
    {
        Log::warning('Escalated SSO: '.$e->getMessage());

        return response('Single sign-on failed. Contact your administrator.', 403);
    }

    protected function requireProvider(string $provider): void
    {
        abort_unless($this->sso->getProvider() === $provider, 404);
    }
}
