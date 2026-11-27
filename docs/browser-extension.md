# Browser extension

Agents can connect the Escalated browser extension to their helpdesk without
giving it their password. The extension opens a connect page in the browser,
the agent approves it, and the extension trades a one-time code for an API
token. The flow follows the staff API contract (v1): a browser redirect with
PKCE (S256).

Everything here needs the REST API on (`escalated.api.enabled`,
`ESCALATED_API_ENABLED`). While it is off, none of these routes exist and
`GET {api prefix}/meta` is not served.

## Pages

| Route name | URL | What it does |
|---|---|---|
| `escalated.agent.extension.connect` | `GET /support/agent/connect-extension` | Shows who is asking (the extension ID and the name it sent) with Approve and Deny |
| `escalated.agent.extension.approve` | `POST /support/agent/connect-extension/approve` | Issues a one-time code and sends the browser back to the extension |
| `escalated.agent.extension.deny` | `POST /support/agent/connect-extension/deny` | Sends the browser back with `error=access_denied` |
| `escalated.agent.extensions.index` | `GET /support/agent/browser-extensions` | The agent's own extension tokens |
| `escalated.agent.extensions.destroy` | `DELETE /support/agent/browser-extensions/{token}` | Revokes one of them |

`/support` is `escalated.routes.prefix`. The routes sit in the agent route
group, so the host's auth, the agent check, two-factor and roles and
permissions apply as on every agent screen. They need no particular
permission. A user who is not signed in is sent to the host's `login` route
and comes back to the connect page with its query intact (Laravel's
`url.intended`). A signed-in user without agent access gets a 403.

`GET /meta` advertises the connect page as `connect_url`.

## How the connect page checks a request

- `redirect_uri` is checked first, on the string exactly as sent. A Chrome
  extension's is `https://<32 letters a to p>.chromiumapp.org/`. When it is
  missing or matches no allowed pattern, the page shows an error (422) and
  redirects nowhere.
- With a valid `redirect_uri`, a missing `state`, a `code_challenge` that is not
  43 base64url characters, or a `code_challenge_method` other than `S256`
  sends the browser back with `error=invalid_request`. No code is issued.
- Approve and Deny run the same checks again on what they are posted.
- `client_name` is display text only. It is cut to 64 characters.
- Opening the page changes nothing. Every code needs a click on Approve, even
  for an extension the agent approved before.
- A code lives 60 seconds and works once.
- Every response from the connect page carries
  `Content-Security-Policy: frame-ancestors 'none'` and `X-Frame-Options: DENY`.

## Configuration

In `config/escalated.php`, under `api`:

```php
'api' => [
    // Lifetime of tokens issued to the extension, in days (null = no expiry).
    'extension_token_expiry_days' => null,

    // Extra redirect_uri patterns besides the Chrome one.
    'extension_redirect_patterns' => [],
],
```

### `extension_token_expiry_days`

Days until a token issued through the connect flow expires. `null` (the
default) means it does not expire until it is revoked.

### `extension_redirect_patterns`

Add a pattern to accept another browser's redirect. Each entry is a PCRE
pattern with delimiters, for example:

```php
'extension_redirect_patterns' => [
    '#\Ahttps://(?<id>[a-z0-9]{32})\.extensions\.example\.org/\z#',
],
```

- The whole match must equal the whole `redirect_uri`. A pattern that matches
  only part of it is ignored, so `^` and `$` are not what makes it safe:
  `$` also matches before a trailing newline, and the `m` modifier turns both
  into line anchors. Write patterns with `\A` and `\z`.
- A named group `id` gives the extension ID that the page shows and the token
  keeps. Without one, the ID is the first label of the host.
- Do not allow a query string or a fragment in the pattern. The code and state
  are added to the URL as a query string.

## Managing tokens

Each agent sees and revokes their own extension tokens at
`/support/agent/browser-extensions`, with each token's name, extension ID,
creation time, last use and expiry. Another user's token is a 404 there.
Extension tokens also appear on the admin API tokens page, where an admin can
revoke them. An extension can revoke its own token with
`POST {api prefix}/auth/revoke`.
