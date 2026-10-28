# Embedding the widget on other sites

The support widget, ticket form and live chat can run on a site other than
the one Escalated is installed on: a store, a marketing site or a customer
portal loads one script from your Escalated install. Nothing changes until you
list the sites that may do this.

## 1. Allow the embedding sites

Either in the environment:

```dotenv
ESCALATED_WIDGET_ALLOWED_ORIGINS=https://shop.example.com,https://*.example.org
```

or in **Admin > Settings > Support Widget > Sites that can embed the widget**
(one per line). Both lists are combined. Each entry is an origin:
`scheme://host[:port]`, no path. `https://*.example.org` matches every
subdomain of `example.org` but not `example.org` itself. A bare `*` is
rejected.

The admin field is stored per tenant when tenancy is on; the environment list
applies to every tenant.

Turn the widget on in the same settings section (`widget_enabled`). Guest
tickets and live chat keep their own switches.

## 2. Add the script to the other site

```html
<script src="https://help.example.com/support/widget/embed.js" async></script>
```

For an on-page support form instead of a floating button:

```html
<div id="support-form"></div>
<script
    src="https://help.example.com/support/widget/embed.js"
    data-mode="inline"
    data-target="#support-form"
    data-tabs="ticket"
    data-department="3"
    data-lock-department="true"
    data-meta-order-number="A-1001"
></script>
```

All host parameters (tabs, department, prefill, metadata, title, the
JavaScript API) are described in the frontend package's
[embedding guide](https://github.com/escalated-dev/escalated/blob/main/docs/embedding-the-widget.md).

## What the server does

For a request whose `Origin` is on the list, the widget routes:

- answer preflight requests and add `Access-Control-Allow-Origin` and
  `Vary: Origin` to every response, including validation errors (422) and
  rate-limit answers (429, with `Retry-After` exposed);
- skip the host's CSRF middleware. The browser sends no session cookie on
  these requests, so there is no token to send and none to protect;
- treat the visitor as a guest even if the same browser is signed in to
  Escalated. A signed-in agent's session is never used from another site.

Requests from any other origin, and same-origin requests, are handled exactly
as before, including the CSRF check. Guest email verification, rate limits and
the `widget_enabled`, `guest_tickets_enabled` and `chat_enabled` switches apply
to embedded requests unchanged. While a switch is off, its routes answer 404
(chat availability still answers `{"available": false}`).

### Host parameters

`/config` returns `accepts_metadata: true`. Widget tickets store the
`metadata` the page sends in `tickets.metadata`; live chats store it in
`chat_metadata` and on the chat session. The values come from the visitor's
browser, so only a flat map is kept: up to 25 entries, keys of letters,
digits, `_ . : -` (64 characters at most), and string, number or boolean
values (strings cut to 500 characters). `page_url` is kept only when it is an
http(s) URL. `embedded_on` is always set by the server from the request's
`Origin` and cannot be supplied by the page. Treat all of it as unverified
visitor input.

### The script

`GET {prefix}/widget/embed.js` serves the bundle shipped in
`resources/dist/widget/escalated-widget.js`, built from
`@escalated-dev/escalated`, with a one-hour cache and an ETag. Set
`escalated.widget.serve_script` to `false` to unmount the route, for example
when you serve the bundle from a CDN. Pages then need `data-base-url`.

## Configuration

```php
'widget' => [
    'allowed_origins' => [...],   // ESCALATED_WIDGET_ALLOWED_ORIGINS, comma separated
    'preflight_max_age' => 600,   // seconds browsers may cache a preflight
    'serve_script' => true,       // mount {prefix}/widget/embed.js
],
```

## Troubleshooting

- **The browser reports a CORS error.** The page's origin is not on the list,
  or it differs in scheme or port (`http://` vs `https://`, `:8080`).
- **Requests return 419.** Same cause: the origin was not recognised, so the
  normal CSRF check ran.
- **The list in `.env` has no effect under `php artisan serve`.** The
  development server only passes certain environment variables to its worker
  process. Use the admin field, or run under a real web server.
- **Laravel's own `config/cors.php`.** It is not needed for the widget routes.
  If you add `support/widget/*` to its `paths`, Laravel's global CORS
  middleware answers those preflights first with its own settings.
