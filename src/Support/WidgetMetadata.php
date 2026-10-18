<?php

namespace Escalated\Laravel\Support;

use Illuminate\Http\Request;

/**
 * Host parameters an embedding page sends with a widget ticket or chat
 * (order number, plan, account id, the page URL, ...). They are visitor
 * supplied, so only a flat map of short scalar values is kept.
 */
class WidgetMetadata
{
    public const MAX_ENTRIES = 25;

    public const MAX_VALUE_LENGTH = 500;

    /** @return array<string, list<string>> */
    public static function rules(): array
    {
        return ['metadata' => ['nullable', 'array', 'max:50']];
    }

    /** @return array<string, scalar>|null */
    public static function fromRequest(Request $request): ?array
    {
        $clean = [];
        $metadata = $request->input('metadata');
        foreach (is_array($metadata) ? $metadata : [] as $key => $value) {
            if (count($clean) >= static::MAX_ENTRIES) {
                break;
            }
            if (! is_string($key) || ! preg_match('/^[A-Za-z0-9_.:-]{1,64}$/', $key)) {
                continue;
            }
            if (is_string($value)) {
                $value = mb_substr(trim($value), 0, static::MAX_VALUE_LENGTH);
            } elseif (! is_int($value) && ! is_float($value) && ! is_bool($value)) {
                continue;
            }
            $clean[$key] = $value;
        }

        if (isset($clean['page_url']) && ! static::isWebUrl($clean['page_url'])) {
            unset($clean['page_url']);
        }

        // Recorded by the server from the request's Origin header, so the
        // page cannot claim another site.
        unset($clean['embedded_on']);
        $origin = $request->attributes->get(WidgetOrigins::ATTRIBUTE);
        if (is_string($origin)) {
            $clean['embedded_on'] = $origin;
        }

        return $clean === [] ? null : $clean;
    }

    protected static function isWebUrl(mixed $value): bool
    {
        return is_string($value) && strlen($value) <= 2048
            && filter_var($value, FILTER_VALIDATE_URL) !== false
            && in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true);
    }
}
