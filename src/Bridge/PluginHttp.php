<?php

namespace Escalated\Laravel\Bridge;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** The additive version 1 HTTP contract within plugin JSON-RPC protocol 1.0. */
class PluginHttp
{
    public static function request(Request $request): array
    {
        return [
            'httpContract' => 1,
            'rawBodyBase64' => base64_encode($request->getContent()),
            'body' => $request->isJson() ? $request->json()->all() : $request->request->all(),
            'params' => $request->route()?->parameters() ?? [],
            'query' => $request->query(),
            'headers' => array_map(fn ($values) => implode(', ', $values), $request->headers->all()),
            'clientIp' => $request->ip(),
        ];
    }

    public static function response(mixed $result): Response
    {
        if (! is_array($result) || ! array_key_exists('__escalated_http', $result)) {
            return response()->json($result ?? []);
        }
        abort_unless(($result['__escalated_http'] ?? null) === 1
            && is_int($result['status'] ?? null) && $result['status'] >= 200 && $result['status'] <= 599
            && in_array($result['format'] ?? null, ['json', 'text'], true)
            && is_array($result['headers'] ?? null) && array_key_exists('body', $result),
            502, 'Invalid plugin HTTP response.');
        abort_if($result['format'] === 'text' && ! is_string($result['body']), 502, 'Invalid plugin text response.');
        $headers = [];
        $blocked = ['connection', 'content-length', 'transfer-encoding', 'keep-alive', 'upgrade',
            'proxy-authenticate', 'proxy-authorization', 'te', 'trailer', 'set-cookie'];
        foreach ($result['headers'] as $name => $value) {
            $name = strtolower((string) $name);
            abort_unless(preg_match('/^[!#$%&\'*+.^_`|~0-9a-z-]+$/D', $name)
                && ! in_array($name, $blocked, true) && ! array_key_exists($name, $headers)
                && is_string($value) && ! preg_match('/[\x00-\x1f\x7f]/', $value),
                502, 'Invalid plugin response header.');
            $headers[$name] = $value;
        }
        if ($result['format'] === 'json') {
            return response()->json($result['body'], $result['status'], $headers);
        }

        return response($result['body'], $result['status'], $headers + ['content-type' => 'text/plain; charset=UTF-8']);
    }
}
