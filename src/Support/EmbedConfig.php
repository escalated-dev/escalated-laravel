<?php

namespace Escalated\Laravel\Support;

use Escalated\Laravel\Http\Middleware\ApplyEmbedContext;
use Escalated\Laravel\Http\Middleware\EnsureIsAdmin;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;

/**
 * Embedding the admin in the host's own admin panel
 * (ADR 2026-11-05-embeddable-admin). Optional and off by default: while
 * `escalated.ui.embed.enabled` is false every method here returns exactly what
 * the routes used before the feature existed.
 *
 * Read from configuration (or Escalated::embedAdmin(), which writes it) when
 * the routes are registered, so a host sets it in config or in a service
 * provider's register() method.
 */
final class EmbedConfig
{
    public const MODES = ['layout', 'iframe'];

    /** Marks a request served by an embedded admin route. */
    public const REQUEST_ATTRIBUTE = 'escalated.embedded';

    public static function enabled(): bool
    {
        return (bool) config('escalated.ui.embed.enabled', false);
    }

    public static function mode(): string
    {
        $mode = config('escalated.ui.embed.mode', 'layout');

        return in_array($mode, self::MODES, true) ? $mode : 'layout';
    }

    /** The host prefix the admin is mounted under while embedded, without slashes. */
    public static function prefix(): string
    {
        $prefix = trim((string) config('escalated.ui.embed.prefix', 'admin/support'), '/');

        return $prefix === '' ? 'admin/support' : $prefix;
    }

    public static function replacesAdminGate(): bool
    {
        return (bool) config('escalated.ui.embed.replace_admin_gate', false);
    }

    public static function layout(): ?string
    {
        $layout = config('escalated.ui.embed.layout');

        return is_string($layout) && $layout !== '' ? $layout : null;
    }

    public static function rootView(): ?string
    {
        $view = config('escalated.ui.embed.root_view');

        return is_string($view) && $view !== '' ? $view : null;
    }

    /**
     * Origins allowed to frame the admin in iframe mode: exact
     * `scheme://host[:port]` origins only. Wildcards, `*`, paths and anything
     * that is not http(s) are dropped.
     *
     * @return list<string>
     */
    public static function frameAncestors(): array
    {
        $configured = config('escalated.ui.embed.frame_ancestors', []);
        if (is_string($configured)) {
            $configured = explode(',', $configured);
        }

        $origins = [];
        foreach ((array) $configured as $entry) {
            $origin = self::origin(is_string($entry) ? trim($entry) : '');
            if ($origin !== null && ! in_array($origin, $origins, true)) {
                $origins[] = $origin;
            }
        }

        return $origins;
    }

    /** The URL prefix of the admin routes: `{routes.prefix}/admin`, or the host's while embedded. */
    public static function adminPrefix(): string
    {
        if (! self::enabled()) {
            return config('escalated.routes.prefix', 'support').'/admin';
        }

        return self::prefix();
    }

    /**
     * Middleware for an admin route group. Without embedding it is exactly
     * `routes.admin_middleware + $extra + $guards`, as before. Embedded, the
     * host's middleware replaces `routes.admin_middleware`, Escalated's admin
     * gate is dropped only if the host opted in, and the embed context runs last.
     *
     * @param  list<string>  $guards  Escalated's own guards for the group
     * @param  list<string>  $extra  middleware between the host's and the guards (e.g. tenancy)
     * @return list<string>
     */
    public static function adminMiddleware(array $guards, array $extra = []): array
    {
        $default = config('escalated.routes.admin_middleware', ['web', 'auth']);

        if (! self::enabled()) {
            return array_merge($default, $extra, $guards);
        }

        $host = config('escalated.ui.embed.middleware');
        $host = is_array($host) ? array_values($host) : $default;

        if (self::replacesAdminGate()) {
            $guards = array_values(array_filter($guards, fn ($guard) => $guard !== EnsureIsAdmin::class));
        }

        return array_merge($host, $extra, $guards, [ApplyEmbedContext::class]);
    }

    public static function isEmbeddedRequest(?Request $request): bool
    {
        return $request !== null && $request->attributes->get(self::REQUEST_ATTRIBUTE) === true;
    }

    /**
     * The `escalated.embed` shared prop. Only shared while embedding is on.
     *
     * @return array<string, mixed>
     */
    public static function shared(?Request $request): array
    {
        $embedded = self::isEmbeddedRequest($request);

        return [
            'enabled' => $embedded,
            'mode' => self::mode(),
            'layout' => self::layout(),
            'route' => $embedded ? $request?->route()?->getName() : null,
            'base_url' => '/'.self::prefix(),
            'allowed_origins' => self::mode() === 'iframe' ? self::frameAncestors() : [],
        ];
    }

    /** The CSP directive for an embedded iframe response. */
    public static function frameAncestorsDirective(): string
    {
        return trim("frame-ancestors 'self' ".implode(' ', self::frameAncestors()));
    }

    /**
     * Iframe mode: once the whole response is built (after any host
     * middleware), allow the configured origins to frame an embedded admin
     * response and drop X-Frame-Options from it, since frame-ancestors is the
     * stricter, allow-listed replacement. A host CSP already on the response
     * is kept; this directive is added as a second policy. No other response
     * is touched.
     */
    public static function decorateResponse(RequestHandled $event): void
    {
        if (! self::enabled() || self::mode() !== 'iframe' || ! self::isEmbeddedRequest($event->request)) {
            return;
        }

        $headers = $event->response->headers;
        $headers->remove('X-Frame-Options');
        $headers->set('Content-Security-Policy', self::frameAncestorsDirective(), false);
    }

    private static function origin(string $value): ?string
    {
        if ($value === '' || str_contains($value, '*')) {
            return null;
        }

        $parts = parse_url($value);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme']);
        if (! in_array($scheme, ['http', 'https'], true) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $path = $parts['path'] ?? '';
        if (($path !== '' && $path !== '/') || isset($parts['query']) || isset($parts['fragment'])) {
            return null;
        }

        $origin = $scheme.'://'.strtolower($parts['host']);

        return isset($parts['port']) ? $origin.':'.$parts['port'] : $origin;
    }
}
