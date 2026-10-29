<?php

namespace Escalated\Laravel\Services;

use Escalated\Laravel\Exceptions\SsoException;
use Escalated\Laravel\Models\EscalatedSettings;
use Escalated\Laravel\Support\AccessFeatures;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessToken;
use OneLogin\Saml2\AuthnRequest;
use OneLogin\Saml2\Constants as SamlConstants;
use OneLogin\Saml2\Response as SamlResponse;
use OneLogin\Saml2\Settings as SamlSettings;
use OneLogin\Saml2\Utils as SamlUtils;

/**
 * Single sign-on through the provider an admin picks in Admin > Settings > SSO
 * (ADR 2026-10-29-configurable-access-features). Crypto is left to
 * maintained libraries: onelogin/php-saml (SAML 2.0), league/oauth2-client
 * (OAuth 2 / OIDC with PKCE) and firebase/php-jwt (JWT).
 *
 * Every validate method returns the same identity shape,
 * ['email' => string, 'name' => string, 'role' => string], or throws an
 * SsoException whose message is for the log, never for the user.
 */
class SsoService
{
    /** How long an AuthnRequest ID stays answerable. */
    public const SAML_REQUEST_TTL = 600;

    /**
     * SSO configuration keys and their defaults.
     */
    protected array $configKeys = [
        'sso_provider' => 'none',
        'sso_entity_id' => '',
        'sso_url' => '',
        'sso_login_url' => '',
        'sso_logout_url' => '',
        'sso_metadata_url' => '',
        'sso_certificate' => '',
        'sso_attr_email' => 'email',
        'sso_attr_name' => 'name',
        'sso_attr_role' => 'role',
        'sso_jwt_secret' => '',
        'sso_jwt_algorithm' => 'HS256',
        'sso_oauth_authorize_url' => '',
        'sso_oauth_token_url' => '',
        'sso_oauth_userinfo_url' => '',
        'sso_oauth_client_id' => '',
        'sso_oauth_client_secret' => '',
        'sso_oauth_scopes' => 'openid profile email',
    ];

    public function __construct(
        protected SsoMessageIds $messageIds,
    ) {}

    /**
     * Get the current SSO configuration.
     */
    public function getConfig(): array
    {
        $config = [];

        foreach ($this->configKeys as $key => $default) {
            $config[$key] = EscalatedSettings::get($key, $default);
        }

        $legacyUrl = trim((string) ($config['sso_url'] ?? ''));
        if ($legacyUrl !== '') {
            if ($config['sso_login_url'] === '') {
                $config['sso_login_url'] = $legacyUrl;
            }

            if ($config['sso_provider'] === 'oauth') {
                if ($config['sso_oauth_authorize_url'] === '') {
                    $config['sso_oauth_authorize_url'] = $legacyUrl;
                }
            } elseif ($config['sso_provider'] === 'saml' && $config['sso_metadata_url'] === '') {
                $config['sso_metadata_url'] = $legacyUrl;
            }
        }

        return $config;
    }

    /**
     * Validate and save SSO configuration.
     */
    public function saveConfig(array $data): void
    {
        $allowedKeys = array_keys($this->configKeys);

        foreach ($data as $key => $value) {
            if (in_array($key, $allowedKeys, true)) {
                EscalatedSettings::set($key, (string) $value);
            }
        }
    }

    /** Whether SSO is switched on for the install and a provider is chosen. */
    public function isEnabled(): bool
    {
        return AccessFeatures::sso() && $this->getProvider() !== 'none';
    }

    /**
     * Get the active SSO provider type.
     */
    public function getProvider(): string
    {
        $provider = (string) EscalatedSettings::get('sso_provider', 'none');

        return in_array($provider, ['saml', 'oauth', 'jwt'], true) ? $provider : 'none';
    }

    /**
     * The URLs an admin gives the identity provider.
     *
     * @return array<string, string>
     */
    public function endpoints(): array
    {
        return [
            'login' => route('escalated.sso.login'),
            'sp_entity_id' => $this->spEntityId(),
            'metadata' => route('escalated.sso.saml.metadata'),
            'acs' => route('escalated.sso.saml.acs'),
            'oauth_redirect' => route('escalated.sso.oauth.callback'),
            'jwt_callback' => route('escalated.sso.jwt.callback'),
        ];
    }

    public function spEntityId(): string
    {
        return (string) (config('escalated.sso.sp_entity_id') ?: route('escalated.sso.saml.metadata'));
    }

    // ------------------------------------------------------------------ SAML

    /**
     * onelogin/php-saml settings. Strict: signatures, Issuer, Audience,
     * Destination, Recipient, validity windows and InResponseTo are checked.
     *
     * @throws SsoException
     */
    public function samlSettings(bool $spOnly = false): SamlSettings
    {
        $config = $this->getConfig();
        $certificate = trim((string) $config['sso_certificate']);

        if (! $spOnly && $certificate === '') {
            throw new SsoException('SAML refused: no IdP certificate is configured.');
        }

        try {
            return new SamlSettings([
                'strict' => true,
                'debug' => false,
                'sp' => [
                    'entityId' => $this->spEntityId(),
                    'assertionConsumerService' => [
                        'url' => route('escalated.sso.saml.acs'),
                        'binding' => SamlConstants::BINDING_HTTP_POST,
                    ],
                    'NameIDFormat' => SamlConstants::NAMEID_UNSPECIFIED,
                ],
                'idp' => [
                    'entityId' => (string) $config['sso_entity_id'],
                    'singleSignOnService' => [
                        'url' => (string) $config['sso_login_url'],
                        'binding' => SamlConstants::BINDING_HTTP_REDIRECT,
                    ],
                    'x509cert' => $certificate,
                ],
                'security' => [
                    // The assertion itself must carry the IdP's signature; a
                    // signed Response around an unsigned assertion is refused.
                    'wantAssertionsSigned' => true,
                    'wantMessagesSigned' => false,
                    'wantNameId' => false,
                    'requestedAuthnContext' => false,
                    'rejectUnsolicitedResponsesWithInResponseTo' => true,
                    'destinationStrictlyMatches' => true,
                    'relaxDestinationValidation' => false,
                    'signatureAlgorithm' => 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256',
                    'digestAlgorithm' => 'http://www.w3.org/2001/04/xmlenc#sha256',
                ],
            ], $spOnly);
        } catch (\Throwable $e) {
            throw new SsoException('SAML settings are invalid: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * The IdP sign-in URL for a new AuthnRequest. Its ID is stored server-side
     * (the IdP's cross-site POST back carries no SameSite=Lax session cookie)
     * and must come back as InResponseTo within SAML_REQUEST_TTL.
     *
     * @throws SsoException
     */
    public function samlLoginUrl(): string
    {
        $settings = $this->samlSettings();
        $request = new AuthnRequest($settings);

        $this->messageIds->remember('saml_request', $request->getId(), time() + self::SAML_REQUEST_TTL);

        $query = http_build_query(['SAMLRequest' => $request->getRequest()]);
        $url = (string) $settings->getIdPData()['singleSignOnService']['url'];

        return $url.(str_contains($url, '?') ? '&' : '?').$query;
    }

    /** @throws SsoException */
    public function samlMetadata(): string
    {
        $settings = $this->samlSettings(true);
        $metadata = $settings->getSPMetadata();
        $errors = $settings->validateMetadata($metadata);

        if ($errors !== []) {
            throw new SsoException('SAML metadata is invalid: '.implode(', ', $errors));
        }

        return $metadata;
    }

    /**
     * Validate a base64 SAMLResponse posted to the ACS.
     *
     * @return array{email: string, name: string, role: string}
     *
     * @throws SsoException
     */
    public function validateSamlResponse(string $samlResponse): array
    {
        $settings = $this->samlSettings();
        $config = $this->getConfig();

        $xml = base64_decode($samlResponse, true);
        if ($xml === false || $xml === '') {
            throw new SsoException('SAML refused: the response is not base64.');
        }
        if (str_contains($xml, 'EncryptedAssertion')) {
            throw new SsoException('SAML refused: encrypted assertions are not supported.');
        }

        $requestId = $this->samlInResponseTo($xml);
        if ($requestId === null) {
            if (! config('escalated.sso.allow_idp_initiated', false)) {
                throw new SsoException('SAML refused: unsolicited response (no InResponseTo) and IdP-initiated sign-in is off.');
            }
        } elseif (! $this->messageIds->consume('saml_request', $requestId)) {
            throw new SsoException("SAML refused: InResponseTo [{$requestId}] is not a pending request.");
        }

        $response = $this->atAcsUrl(function () use ($settings, $samlResponse, $requestId) {
            try {
                $response = new SamlResponse($settings, $samlResponse);
            } catch (\Throwable $e) {
                throw new SsoException('SAML refused: '.$e->getMessage(), 0, $e);
            }

            if (! $response->isValid($requestId)) {
                throw new SsoException('SAML refused: '.$response->getError(false));
            }

            return $response;
        });

        $assertionId = (string) $response->getAssertionId();
        $expires = (int) ($response->getAssertionNotOnOrAfter() ?: time() + 300) + SamlConstants::ALLOWED_CLOCK_DRIFT;
        if ($assertionId === '' || ! $this->messageIds->claim('saml_assertion', $assertionId, $expires)) {
            throw new SsoException("SAML refused: assertion [{$assertionId}] was already used.");
        }

        $attributes = $response->getAttributes();
        $first = fn (string $name) => isset($attributes[$name][0]) ? trim((string) $attributes[$name][0]) : '';

        $email = $first((string) $config['sso_attr_email']);
        if ($email === '') {
            $nameId = trim((string) $response->getNameId());
            $email = filter_var($nameId, FILTER_VALIDATE_EMAIL) ? $nameId : '';
        }

        return $this->identity($email, $first((string) $config['sso_attr_name']), $first((string) $config['sso_attr_role']));
    }

    /**
     * Run a php-saml check as if the request arrived at the configured ACS
     * URL, so Destination and Recipient are compared with that URL and not
     * with whatever host and path a proxy happened to forward.
     */
    protected function atAcsUrl(callable $callback): mixed
    {
        $acs = parse_url(route('escalated.sso.saml.acs'));
        $scheme = $acs['scheme'] ?? 'https';
        $savedUri = $_SERVER['REQUEST_URI'] ?? null;
        $savedQuery = $_SERVER['QUERY_STRING'] ?? null;

        SamlUtils::setSelfProtocol($scheme);
        SamlUtils::setSelfHost($acs['host'] ?? 'localhost');
        SamlUtils::setSelfPort((string) ($acs['port'] ?? ($scheme === 'https' ? 443 : 80)));
        SamlUtils::setBaseURLPath(null);
        $_SERVER['REQUEST_URI'] = $acs['path'] ?? '/';
        unset($_SERVER['QUERY_STRING']);

        try {
            return $callback();
        } finally {
            SamlUtils::setSelfProtocol(null);
            SamlUtils::setSelfHost(null);
            SamlUtils::setSelfPort(null);
            $savedUri === null ? $_SERVER['REQUEST_URI'] = '' : $_SERVER['REQUEST_URI'] = $savedUri;
            if ($savedQuery !== null) {
                $_SERVER['QUERY_STRING'] = $savedQuery;
            }
        }
    }

    protected function samlInResponseTo(string $xml): ?string
    {
        $doc = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $doc->loadXML($xml, LIBXML_NONET);
        libxml_use_internal_errors($previous);

        if (! $loaded || ! $doc->documentElement) {
            throw new SsoException('SAML refused: malformed XML.');
        }

        $value = trim($doc->documentElement->getAttribute('InResponseTo'));

        return $value === '' ? null : $value;
    }

    // ----------------------------------------------------------------- OAuth

    public function oauthProvider(): GenericProvider
    {
        $config = $this->getConfig();
        $scopes = preg_split('/[\s,]+/', trim((string) $config['sso_oauth_scopes'])) ?: [];

        $collaborators = [];
        if (app()->bound('escalated.sso.http_client')) {
            $collaborators['httpClient'] = app('escalated.sso.http_client');
        }

        return new GenericProvider([
            'clientId' => (string) $config['sso_oauth_client_id'],
            'clientSecret' => (string) $config['sso_oauth_client_secret'],
            'redirectUri' => route('escalated.sso.oauth.callback'),
            'urlAuthorize' => (string) $config['sso_oauth_authorize_url'],
            'urlAccessToken' => (string) $config['sso_oauth_token_url'],
            'urlResourceOwnerDetails' => (string) $config['sso_oauth_userinfo_url'],
            'scopes' => array_values(array_filter($scopes)),
            'scopeSeparator' => ' ',
            'pkceMethod' => GenericProvider::PKCE_METHOD_S256,
        ], $collaborators);
    }

    /**
     * Exchange the authorization code (with the PKCE verifier) server to
     * server and read the profile from the userinfo endpoint.
     *
     * @return array{email: string, name: string, role: string}
     *
     * @throws SsoException
     */
    public function oauthIdentity(string $code, string $pkceVerifier): array
    {
        $config = $this->getConfig();
        $provider = $this->oauthProvider();
        $provider->setPkceCode($pkceVerifier);

        try {
            /** @var AccessToken $token */
            $token = $provider->getAccessToken('authorization_code', ['code' => $code]);
            $profile = $provider->getResourceOwner($token)->toArray();
        } catch (\Throwable $e) {
            throw new SsoException('OAuth refused: '.$e->getMessage(), 0, $e);
        }

        if (array_key_exists('email_verified', $profile) && in_array($profile['email_verified'], [false, 'false', 0, '0'], true)) {
            throw new SsoException('OAuth refused: the provider says the email is not verified.');
        }

        $claim = fn (string $name, string $fallback = '') => trim((string) (data_get($profile, $name) ?? data_get($profile, $fallback) ?? ''));

        return $this->identity(
            $claim((string) $config['sso_attr_email'], 'email'),
            $claim((string) $config['sso_attr_name'], 'name'),
            $claim((string) $config['sso_attr_role']),
        );
    }

    // ------------------------------------------------------------------- JWT

    /**
     * Validate a JWT handed back by the identity provider.
     *
     * @return array{email: string, name: string, role: string}
     *
     * @throws SsoException
     */
    public function validateJwtToken(string $token): array
    {
        $config = $this->getConfig();
        $algorithm = (string) ($config['sso_jwt_algorithm'] ?: 'HS256');
        $secret = (string) $config['sso_jwt_secret'];

        if (! in_array($algorithm, ['HS256', 'HS384', 'HS512', 'RS256', 'RS384', 'RS512'], true)) {
            throw new SsoException("JWT refused: unsupported algorithm {$algorithm}.");
        }
        if ($secret === '') {
            throw new SsoException('JWT refused: no secret or public key is configured.');
        }

        $previousLeeway = JWT::$leeway;
        JWT::$leeway = $this->clockSkew();

        try {
            // The Key pins the algorithm: a token naming any other alg
            // (including "none") is refused by the library.
            $claims = (array) JWT::decode($token, new Key($secret, $algorithm));
        } catch (\Throwable $e) {
            throw new SsoException('JWT refused: '.$e->getMessage(), 0, $e);
        } finally {
            JWT::$leeway = $previousLeeway;
        }

        if (! isset($claims['exp']) || ! is_numeric($claims['exp'])) {
            throw new SsoException('JWT refused: no exp claim.');
        }

        $audience = (string) (config('escalated.sso.jwt_audience') ?: config('app.url'));
        $tokenAudiences = array_map('strval', (array) ($claims['aud'] ?? []));
        if ($audience === '' || ! in_array($audience, $tokenAudiences, true)) {
            throw new SsoException('JWT refused: aud does not name this application.');
        }

        $jti = isset($claims['jti']) ? (string) $claims['jti'] : '';
        if ($jti === '') {
            throw new SsoException('JWT refused: no jti claim.');
        }
        if (! $this->messageIds->claim('jwt', $jti, (int) $claims['exp'] + $this->clockSkew())) {
            throw new SsoException("JWT refused: jti [{$jti}] was already used.");
        }

        $claim = fn (string $name, string $fallback = '') => trim((string) ($claims[$name] ?? ($fallback !== '' ? ($claims[$fallback] ?? '') : '')));

        return $this->identity(
            $claim((string) $config['sso_attr_email'], 'email'),
            $claim((string) $config['sso_attr_name'], 'name'),
            $claim((string) $config['sso_attr_role']),
        );
    }

    public function clockSkew(): int
    {
        return max(0, min(300, (int) config('escalated.sso.clock_skew', 120)));
    }

    /**
     * @return array{email: string, name: string, role: string}
     *
     * @throws SsoException
     */
    protected function identity(string $email, string $name, string $role): array
    {
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new SsoException('SSO refused: no valid email in the identity.');
        }

        return ['email' => $email, 'name' => $name, 'role' => $role];
    }
}
