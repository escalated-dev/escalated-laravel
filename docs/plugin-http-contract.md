# Plugin HTTP transport

Laravel consumes the SDK's endpoint/webhook manifest arrays (`method`, `path`,
optional `capability`) as well as legacy maps keyed by `METHOD /path`.
Data endpoints still require the configured admin middleware, the admin gate and
any declared capability. Webhooks are public; their handlers must authenticate
the sender before challenge responses or side effects. Tenant-enabled hosts
continue to reject plugin execution until the runtime supports tenant isolation.

HTTP calls require a runtime that advertises `http_contract_versions: [1]` in
its JSON-RPC 1.0 handshake. An older runtime returns HTTP 503 for these calls;
ordinary action/filter calls retain their existing protocol. Upgrade the runtime
before deploying this bridge. The default command uses the runtime package's
actual `build/bin/escalated-plugins.js` entry point; custom commands are preserved.

The host sends `httpContract: 1`, `rawBodyBase64` from the exact request content,
parsed `body`, distinct route `params` and `query`, lowercase scalar `headers`,
and `clientIp` from Laravel's trusted-proxy policy. Repeated headers remain
comma-separated, so signature handlers must reject an ambiguous signature.
The runtime decodes raw bytes to the SDK's `req.rawBody`; JSON serialization of
the parsed body must never be used to verify signatures. Hosts must configure
trusted proxies correctly before using clientIp for security decisions.

An explicit SDK `httpResponse()` envelope carries `__escalated_http: 1`, an
integer status from 200 to 599, `format` (`json` or `text`), headers and body.
The bridge renders that HTTP status and content, validates it independently and
rejects malformed envelopes with 502. Header injection, duplicate header names,
transport headers and Set-Cookie are rejected. Text bodies must be strings and
default to text/plain. Ordinary returned objects remain JSON with status 200;
an ordinary `{status: 401}` object does not select the HTTP status.

This change provides transport prerequisites. Slack signature verification,
tenant/workspace routing, durable event deduplication and ticket/reply ingestion
still require an inbound adapter. It does not release the web-widget plugin or
change CORS policy.
