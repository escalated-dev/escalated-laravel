<?php

namespace Escalated\Laravel\Support;

use Escalated\Laravel\Models\EscalatedSettings;
use Illuminate\Http\Request;

/**
 * Decides which other sites may embed the widget. The list comes from
 * config('escalated.widget.allowed_origins') plus the admin setting
 * `widget_allowed_origins` (one origin per line or comma separated).
 */
class WidgetOrigins
{
    /** Request attribute set when a request comes from an allowed other site. */
    public const ATTRIBUTE = 'escalated.widget.cross_origin';

    /**
     * The request's Origin when it names another site, or null for
     * same-origin requests and requests without an Origin header.
     */
    public static function crossOrigin(Request $request): ?string
    {
        $origin = $request->headers->get('Origin');
        if (! is_string($origin) || $origin === '' || $origin === 'null') {
            return null;
        }

        $origin = static::normalize($origin);
        if ($origin === null || $origin === static::normalize($request->getSchemeAndHttpHost())) {
            return null;
        }

        return $origin;
    }

    public static function allows(string $origin): bool
    {
        return static::matchesAny(static::configured(), $origin);
    }

    /** Whether the config list alone allows the origin (no settings read). */
    public static function allowsConfigured(string $origin): bool
    {
        return static::matchesAny(static::patterns((array) config('escalated.widget.allowed_origins', [])), $origin);
    }

    /** @param  list<string>  $patterns */
    protected static function matchesAny(array $patterns, string $origin): bool
    {
        $origin = static::normalize($origin);
        if ($origin === null) {
            return false;
        }

        foreach ($patterns as $pattern) {
            if (static::matches($pattern, $origin)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    public static function configured(): array
    {
        $entries = (array) config('escalated.widget.allowed_origins', []);
        $setting = (string) EscalatedSettings::get('widget_allowed_origins', '');
        if ($setting !== '') {
            $entries = array_merge($entries, preg_split('/[\s,]+/', $setting) ?: []);
        }

        return static::patterns($entries);
    }

    /**
     * @param  array<mixed>  $entries
     * @return list<string>
     */
    protected static function patterns(array $entries): array
    {
        $patterns = [];
        foreach ($entries as $entry) {
            $pattern = static::normalizePattern((string) $entry);
            if ($pattern !== null) {
                $patterns[$pattern] = true;
            }
        }

        return array_keys($patterns);
    }

    /**
     * Normalise an admin-entered origin or "*." pattern, or null when it is
     * not a bare http(s) origin.
     */
    public static function normalizePattern(string $entry): ?string
    {
        $entry = trim($entry);
        if (! preg_match('#^(https?)://(\*\.)?([^/?\#*]+?)/?$#i', $entry, $m)) {
            return null;
        }

        $host = static::normalize(strtolower($m[1]).'://'.$m[3]);

        return $host === null ? null : ($m[2] !== '' ? str_replace('://', '://*.', $host) : $host);
    }

    protected static function matches(string $pattern, string $origin): bool
    {
        if (! str_contains($pattern, '://*.')) {
            return $pattern === $origin;
        }

        [$scheme, $suffix] = explode('://*.', $pattern, 2);
        if (! str_starts_with($origin, $scheme.'://')) {
            return false;
        }
        $host = substr($origin, strlen($scheme) + 3);

        return str_ends_with($host, '.'.$suffix) && strlen($host) > strlen($suffix) + 1;
    }

    /** scheme://host[:port], lowercased, default ports dropped. */
    protected static function normalize(string $origin): ?string
    {
        $parts = parse_url(trim($origin));
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower($parts['host'] ?? '');
        if (! in_array($scheme, ['http', 'https'], true) || $host === ''
            || isset($parts['user']) || isset($parts['query']) || isset($parts['fragment'])
            || (($parts['path'] ?? '') !== '' && $parts['path'] !== '/')) {
            return null;
        }

        $port = $parts['port'] ?? null;
        if ($port === null || ($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443)) {
            return $scheme.'://'.$host;
        }

        return $scheme.'://'.$host.':'.$port;
    }
}
