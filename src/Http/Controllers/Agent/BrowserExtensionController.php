<?php

namespace Escalated\Laravel\Http\Controllers\Agent;

use Escalated\Laravel\Contracts\EscalatedUiRenderer;
use Escalated\Laravel\Http\InertiaErrorPage;
use Escalated\Laravel\Models\ApiToken;
use Escalated\Laravel\Models\ApiTokenCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Routing\Controller;
use Illuminate\Routing\Router;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * The browser extension connect page and the agent's own list of extension
 * tokens (staff API contract v1, "Connecting an extension" and "Connect page
 * requirements").
 *
 * The page checks redirect_uri before anything else and, when it is missing
 * or invalid, shows an error page and never redirects. With a valid
 * redirect_uri, any other bad parameter goes back to it as invalid_request.
 * Approve and Deny run the same checks on what they are posted: the GET is
 * never trusted to have done it. GET has no side effects, and nothing is ever
 * approved without a click, even for an extension approved before.
 */
class BrowserExtensionController extends Controller
{
    public const KIND = 'browser_extension';

    /** Seconds a one-time code lives. */
    public const CODE_TTL = 60;

    public const CLIENT_NAME_MAX = 64;

    /** The Chrome extension redirect, as a whole string. */
    protected const CHROME_REDIRECT = '/\Ahttps:\/\/([a-p]{32})\.chromiumapp\.org\/\z/';

    /** BASE64URL(SHA-256(verifier)) without padding is always 43 characters. */
    protected const CODE_CHALLENGE = '/\A[A-Za-z0-9_-]{43}\z/';

    public function __construct(
        protected EscalatedUiRenderer $renderer,
    ) {}

    public function show(Request $request): mixed
    {
        $params = $this->rawQuery($request);

        $redirect = $this->redirectTarget($params['redirect_uri'] ?? null);
        if ($redirect === null) {
            return $this->invalidRedirectPage($request);
        }

        if (! $this->validRequest($params)) {
            return $this->redirectTo($request, $redirect, ['error' => 'invalid_request', 'state' => $this->state($params)]);
        }

        $clientName = $this->clientName($params);

        return $this->renderer->render('Escalated/Agent/ConnectExtension', [
            'client_name' => $clientName,
            'extension_id' => $redirect['extension_id'],
            'origin_host' => $redirect['host'],
            'approve_url' => route('escalated.agent.extension.approve'),
            'deny_url' => route('escalated.agent.extension.deny'),
            'params' => [
                'redirect_uri' => $redirect['uri'],
                'state' => $this->state($params),
                'code_challenge' => $params['code_challenge'],
                'code_challenge_method' => $params['code_challenge_method'],
                'client_name' => $clientName,
            ],
        ]);
    }

    public function approve(Request $request): Response
    {
        $params = $this->rawBody($request);

        $redirect = $this->redirectTarget($params['redirect_uri'] ?? null);
        if ($redirect === null) {
            return $this->invalidRedirectPage($request);
        }

        if (! $this->validRequest($params)) {
            return $this->redirectTo($request, $redirect, ['error' => 'invalid_request', 'state' => $this->state($params)]);
        }

        $code = ApiTokenCode::issue(
            $request->user(),
            $params['code_challenge'],
            $redirect['uri'],
            $this->clientName($params),
            $redirect['extension_id'],
            self::CODE_TTL,
        );

        return $this->redirectTo($request, $redirect, ['code' => $code, 'state' => $this->state($params)]);
    }

    public function deny(Request $request): Response
    {
        $params = $this->rawBody($request);

        $redirect = $this->redirectTarget($params['redirect_uri'] ?? null);
        if ($redirect === null) {
            return $this->invalidRedirectPage($request);
        }

        if (! $this->validRequest($params)) {
            return $this->redirectTo($request, $redirect, ['error' => 'invalid_request', 'state' => $this->state($params)]);
        }

        return $this->redirectTo($request, $redirect, ['error' => 'access_denied', 'state' => $this->state($params)]);
    }

    /** The signed-in user's own browser extension tokens. */
    public function index(Request $request): mixed
    {
        $tokens = $this->ownTokens($request)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (ApiToken $token) => [
                'id' => $token->id,
                'name' => $token->name,
                'extension_id' => $token->extension_id,
                'created_at' => $token->created_at?->toIso8601String(),
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'expires_at' => $token->expires_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return $this->renderer->render('Escalated/Agent/BrowserExtensions', [
            'tokens' => $tokens,
            'revoke_url_template' => route('escalated.agent.extensions.destroy', ['token' => '__ID__']),
        ]);
    }

    /** Another user's token, or one that is not an extension's, is a 404. */
    public function destroy(Request $request, string $token): RedirectResponse
    {
        $this->ownTokens($request)->whereKey($token)->firstOrFail()->delete();

        return redirect()->back(303, [], route('escalated.agent.extensions.index'));
    }

    protected function ownTokens(Request $request)
    {
        $user = $request->user();

        return ApiToken::query()
            ->where('tokenable_type', $user->getMorphClass())
            ->where('tokenable_id', $user->getKey())
            ->where('kind', self::KIND);
    }

    /**
     * The validated redirect: the exact URI, the URL the answer is built on,
     * its host and the extension ID. Null when redirect_uri is missing or
     * matches no allowed pattern as a whole string.
     *
     * @return array{uri: string, base: string, host: string, extension_id: string}|null
     */
    protected function redirectTarget(mixed $uri): ?array
    {
        if (! is_string($uri) || $uri === '') {
            return null;
        }

        if (preg_match(self::CHROME_REDIRECT, $uri, $match) === 1) {
            $host = $match[1].'.chromiumapp.org';

            return [
                'uri' => $uri,
                'base' => 'https://'.$host.'/',
                'host' => $host,
                'extension_id' => $match[1],
            ];
        }

        foreach ((array) config('escalated.api.extension_redirect_patterns', []) as $pattern) {
            // The whole match must be the whole string, whatever anchors (or
            // modifiers) the pattern uses: `$` alone lets a trailing newline
            // through, and `m` makes `^` and `$` line anchors.
            if (! is_string($pattern) || preg_match($pattern, $uri, $match) !== 1 || $match[0] !== $uri) {
                continue;
            }

            $host = (string) parse_url($uri, PHP_URL_HOST);
            $extensionId = isset($match['id']) && is_string($match['id']) && $match['id'] !== ''
                ? $match['id']
                : explode('.', $host)[0];

            if ($host === '' || $extensionId === '') {
                continue;
            }

            return [
                'uri' => $uri,
                'base' => $uri,
                'host' => $host,
                'extension_id' => $extensionId,
            ];
        }

        return null;
    }

    /**
     * The query string as sent. redirect_uri is checked on the raw string and
     * stored for an exact comparison at exchange, so it is read before the
     * host's TrimStrings / ConvertEmptyStringsToNull middleware touch it.
     *
     * @return array<string, mixed>
     */
    protected function rawQuery(Request $request): array
    {
        return HeaderUtils::parseQuery((string) $request->server->get('QUERY_STRING', ''));
    }

    /**
     * The posted params as sent (JSON from the Inertia page, or a urlencoded
     * form), for the same reason as rawQuery(). Only the body counts: a
     * query string on the POST is ignored.
     *
     * @return array<string, mixed>
     */
    protected function rawBody(Request $request): array
    {
        if ($request->isJson()) {
            $decoded = json_decode($request->getContent(), true);

            return is_array($decoded) ? $decoded : [];
        }

        $content = (string) $request->getContent();
        if ($content !== '' && str_starts_with((string) $request->headers->get('Content-Type'), 'application/x-www-form-urlencoded')) {
            return HeaderUtils::parseQuery($content);
        }

        return $request->request->all();
    }

    /** @param  array<string, mixed>  $params */
    protected function validRequest(array $params): bool
    {
        $challenge = $params['code_challenge'] ?? null;
        $method = $params['code_challenge_method'] ?? null;

        return $this->state($params) !== null
            && is_string($challenge) && preg_match(self::CODE_CHALLENGE, $challenge) === 1
            && $method === 'S256';
    }

    /** @param  array<string, mixed>  $params */
    protected function state(array $params): ?string
    {
        $state = $params['state'] ?? null;

        // A state that is not UTF-8 cannot be shown back to the page, so it
        // counts as missing.
        return is_string($state) && $state !== '' && mb_check_encoding($state, 'UTF-8') ? $state : null;
    }

    /** @param  array<string, mixed>  $params */
    protected function clientName(array $params): string
    {
        $name = $params['client_name'] ?? null;

        return is_string($name) ? mb_substr(mb_scrub($name, 'UTF-8'), 0, self::CLIENT_NAME_MAX, 'UTF-8') : '';
    }

    /**
     * Send the browser to the validated redirect with the given answer. An
     * Inertia visit (Approve and Deny post over XHR) gets a 409 with
     * X-Inertia-Location so the page does a full navigation.
     *
     * @param  array{base: string}  $redirect
     * @param  array<string, string|null>  $query
     */
    protected function redirectTo(Request $request, array $redirect, array $query): Response
    {
        $query = array_filter($query, fn ($value) => $value !== null);
        $separator = str_contains($redirect['base'], '?') ? '&' : '?';
        $url = $redirect['base'].$separator.http_build_query($query, '', '&', PHP_QUERY_RFC3986);

        // What Inertia::location() answers, without tying the controller to
        // Inertia (a host may render Escalated with another UI).
        $response = $request->header('X-Inertia')
            ? new HttpResponse('', 409, ['X-Inertia-Location' => $url])
            : redirect()->away($url);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    /** The error page for a missing or invalid redirect_uri. It never redirects. */
    protected function invalidRedirectPage(Request $request): Response
    {
        $page = $this->renderer->render(InertiaErrorPage::PAGE, [
            'status' => 422,
            'message' => __('escalated::messages.errors.extension_connect'),
        ]);

        return Router::toResponse($request, $page)->setStatusCode(422);
    }
}
