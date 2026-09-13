<?php

namespace Escalated\Laravel\Support;

/**
 * Decides whether the package may send a request to a URL that someone typed
 * into the admin UI: a webhook endpoint, or a workflow's send_webhook action.
 *
 * The URL must be http or https, and every address its host resolves to must
 * be public. Loopback, private, link-local (cloud metadata sits at
 * 169.254.169.254) and other reserved ranges are refused, and so is a host that
 * does not resolve at all.
 *
 * Resolved from the container, so a test can replace resolve() instead of
 * depending on real DNS.
 */
class OutboundUrlGuard
{
    public function allows(string $url, ?callable $resolver = null): bool
    {
        return $this->publicAddressFor($url, $resolver) !== null;
    }

    /**
     * The public address a request to the URL should connect to, or null when
     * the URL must not be requested.
     *
     * A $resolver, when given, resolves the host instead of resolve(). Any
     * value it returns that is not an IP (such as the host name that
     * gethostbyname() hands back on failure) counts as unresolved.
     *
     * @param  (callable(string): (string|list<string>))|null  $resolver
     */
    public function publicAddressFor(string $url, ?callable $resolver = null): ?string
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $host = trim((string) parse_url($url, PHP_URL_HOST), '[]');

        if ($host === '') {
            return null;
        }

        $resolved = $resolver !== null ? (array) $resolver($host) : $this->resolve($host);
        $addresses = array_values(array_filter(
            $resolved,
            fn ($address) => is_string($address) && filter_var($address, FILTER_VALIDATE_IP) !== false,
        ));

        if ($addresses === []) {
            return null;
        }

        foreach ($addresses as $address) {
            if (! $this->isPublic($address)) {
                return null;
            }
        }

        return $addresses[0];
    }

    /**
     * Every address the host resolves to. An IP literal resolves to itself.
     *
     * @return list<string>
     */
    public function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $addresses = gethostbynamel($host);

        return $addresses === false ? [] : array_values($addresses);
    }

    protected function isPublic(string $address): bool
    {
        return filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE | FILTER_FLAG_GLOBAL_RANGE,
        ) !== false;
    }
}
