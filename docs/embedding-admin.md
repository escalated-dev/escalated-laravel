# Embedding the admin in your own admin panel (optional)

Escalated ships a complete standalone admin at `/support/admin` (your
`escalated.routes.prefix` + `/admin`), with its own sidebar and top bar. **That
is the default, and it needs no setup.** If your Laravel app already has an
admin panel, you can instead show Escalated's admin screens inside it: under
your sidebar and header, your URL prefix and your middleware.

This is an optional alternative to the standalone admin. Nothing on this page
has any effect until you turn it on: with no embed configuration every route,
page, prop, header and gate is exactly what it was before. The design is in
the ADR
[`2026-11-05-embeddable-admin`](https://github.com/escalated-dev/escalated-developer-context/blob/main/decisions/2026-11-05-embeddable-admin.md);
the frontend side is documented in
[`@escalated-dev/escalated` docs/embedding-admin.md](https://github.com/escalated-dev/escalated/blob/main/docs/embedding-admin.md).

## Contents

- [Pick your setup](#pick-your-setup)
- [Turning it on](#turning-it-on)
- [Configuration reference](#configuration-reference)
- [What changes, and what does not](#what-changes-and-what-does-not)
- [1. Vue + Inertia host](#1-vue--inertia-host)
- [2. Blade host](#2-blade-host)
- [3. iframe](#3-iframe)
- [The admin menu: `escalated.navigation`](#the-admin-menu-escalatednavigation)
- [Security](#security)
- [Turning it off](#turning-it-off)

## Pick your setup

| Your admin panel | Use | `mode` |
|---|---|---|
| Inertia + Vue (Breeze, Jetstream, a starter kit, your own) | [Vue + Inertia host](#1-vue--inertia-host) | `layout` |
| Blade templates | [Blade host](#2-blade-host) (your Blade layout as the root view) | `layout` |
| Another app or origin, a non-Vue SPA, a CMS admin | [iframe](#3-iframe) | `iframe` |

## Turning it on

Either call `Escalated::embedAdmin()` from a service provider's **`register()`**
method (it must run before Escalated registers its routes, which happens while
providers boot):

```php
// app/Providers/AppServiceProvider.php
use Escalated\Laravel\Escalated;

public function register(): void
{
    Escalated::embedAdmin(
        prefix: 'admin/support',
        middleware: ['web', 'auth', 'can:access-admin'],
        layout: 'Layouts/AdminLayout',
    );
}
```

or set the keys in `config/escalated.php` (publish it with
`php artisan vendor:publish --tag=escalated-config` if you have not):

```php
'ui' => [
    'enabled' => env('ESCALATED_UI_ENABLED', true),
    'embed' => [
        'enabled' => env('ESCALATED_EMBED', false),
        'mode' => env('ESCALATED_EMBED_MODE', 'layout'),
        'prefix' => env('ESCALATED_EMBED_PREFIX', 'admin/support'),
        'middleware' => ['web', 'auth', 'can:access-admin'],
        'replace_admin_gate' => false,
        'layout' => 'Layouts/AdminLayout',
        'root_view' => null,
        'frame_ancestors' => [],
    ],
],
```

If your published config predates this feature it has no `embed` block; the
defaults below apply, so it stays off. Run `php artisan route:clear` (or
re-cache) after changing it.

## Configuration reference

| Key (`escalated.ui.embed.*`) | Env var | Default | Meaning |
|---|---|---|---|
| `enabled` | `ESCALATED_EMBED` | `false` | Master switch. While `false`, nothing below has any effect. |
| `mode` | `ESCALATED_EMBED_MODE` | `'layout'` | `'layout'` (Vue + Inertia or Blade host) or `'iframe'`. |
| `prefix` | `ESCALATED_EMBED_PREFIX` | `'admin/support'` | URL prefix the admin moves under. |
| `middleware` | | `null` | Middleware for the admin routes. `null` keeps `escalated.routes.admin_middleware` (`['web', 'auth']`). |
| `replace_admin_gate` | | `false` | `true` removes Escalated's admin gate from these routes so your middleware alone decides. See [Security](#security). |
| `layout` | | `null` | Name of your Vue admin layout, matched against the frontend plugin's `layouts` option. |
| `root_view` | | `null` | Blade root view for embedded pages ([Blade host](#2-blade-host)). |
| `frame_ancestors` | `ESCALATED_EMBED_FRAME_ANCESTORS` (comma-separated) | `[]` | iframe mode: exact origins allowed to frame the admin. Wildcards and `*` are ignored. |

`Escalated::embedAdmin()` takes the same settings as named arguments:
`prefix`, `middleware`, `layout`, `rootView`, `mode`, `replaceAdminGate`,
`frameAncestors`.

## What changes, and what does not

While embedding is on:

- Every `escalated.admin.*` route that lived under `/support/admin` (the admin
  screens, plugin admin, API tokens), and the SDK plugins' admin pages, are registered under your
  `prefix` with your `middleware`, followed by Escalated's own guards
  (`EnsureIsAdmin` unless you replace it, `RequireTwoFactor`,
  `EnforceRoutePermissions`). **Route names do not change**, so the shared
  frontend's `route('escalated.admin.…')` links follow. The old
  `/support/admin/...` URLs answer 404.
- Embedded pages carry `escalated.embed` (see below) and every Inertia page
  carries `escalated.navigation`, the admin menu for your sidebar.

Unchanged: the agent panel (`/support/agent`), customer portal, guest pages,
widget, API, SSO, the two-factor enrolment page and challenge (personal,
staff-wide pages), and the newsletter admin (`/admin/newsletters`).

`escalated.embed` on an embedded page:

```json
{
  "enabled": true,
  "mode": "layout",
  "layout": "Layouts/AdminLayout",
  "route": "escalated.admin.tickets.index",
  "base_url": "/admin/support",
  "allowed_origins": []
}
```

On every other page `enabled` is `false` and `route` is `null`.

## 1. Vue + Inertia host

Backend: turn embedding on (above) with your prefix and admin middleware.

Frontend (`resources/js/app.js`): add `admin` to the plugin's `layoutScope`,
or register your admin layout under the name you gave `layout`:

```js
import { EscalatedPlugin, resolveEscalatedPage } from '@escalated-dev/escalated';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

createInertiaApp({
    resolve: (name) => resolveEscalatedPage(name, () =>
        resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue'))),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(EscalatedPlugin, {
                layout: AuthenticatedLayout, // customer pages, as before
                layouts: { 'Layouts/AdminLayout': AdminLayout }, // embedded admin pages
                theme: { panel: { mode: 'light' } }, // match your admin
            })
            .mount(el);
    },
});
```

Your admin layout needs the same slots the plugin's `layout` always required, a
`#header` slot (Escalated puts the page title there) and a default slot. List
Escalated's menu with `<EscalatedNavigation />`:

```vue
<!-- resources/js/Layouts/AdminLayout.vue -->
<script setup>
import { EscalatedNavigation } from '@escalated-dev/escalated';
</script>

<template>
    <div class="flex min-h-screen">
        <aside class="w-64 border-r bg-white p-3">
            <Link :href="route('dashboard')">Dashboard</Link>
            <!-- …your own items… -->
            <p class="mt-4 px-3 text-xs font-semibold uppercase text-gray-500">Support</p>
            <EscalatedNavigation />
        </aside>
        <div class="flex-1">
            <header class="border-b bg-white px-6 py-4"><slot name="header" /></header>
            <main class="p-6"><slot /></main>
        </div>
    </div>
</template>
```

Escalated renders each admin page chrome-free (no Escalated sidebar, top bar,
logo or footer) inside your layout. To use the same layout for customer pages
too, use `layout: AdminLayout, layoutScope: ['customer', 'admin']` instead of
`layouts`.

## 2. Blade host

If your admin layout is a Blade template, make a root view that contains your
chrome and the Inertia mount point, and point `root_view` at it:

```php
Escalated::embedAdmin(
    prefix: 'admin/support',
    middleware: ['web', 'auth', 'can:access-admin'],
    rootView: 'admin.escalated',
);
```

```blade
{{-- resources/views/admin/escalated.blade.php --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body>
    @include('admin.partials.header')
    <div class="flex">
        <aside class="w-64">
            @include('admin.partials.menu')
            <p class="menu-heading">Support</p>
            <ul>
                @foreach (\Escalated\Laravel\Escalated::adminNavigation() as $item)
                    <li>
                        <a href="{{ $item['url'] }}" @class(['active' => $item['active']])
                           @if ($item['active']) aria-current="page" @endif>{{ $item['label'] }}</a>
                    </li>
                @endforeach
            </ul>
        </aside>
        <main class="flex-1">@inertia</main>
    </div>
</body>
</html>
```

Only embedded pages use this root view; the rest of Escalated keeps your normal
one. Your Inertia entry needs no extra option: Escalated sees
`escalated.embed.enabled` and renders the page chrome-free (title + content).

## 3. iframe

For an admin that is not part of this app (another origin, another stack),
frame the embedded admin:

```php
Escalated::embedAdmin(
    prefix: 'admin/support',
    middleware: ['web', 'auth'],
    mode: 'iframe',
    frameAncestors: ['https://admin.example.com'],
);
```

or `ESCALATED_EMBED=true`, `ESCALATED_EMBED_MODE=iframe`,
`ESCALATED_EMBED_FRAME_ANCESTORS=https://admin.example.com`.

Embedded responses then carry
`Content-Security-Policy: frame-ancestors 'self' https://admin.example.com`
and no `X-Frame-Options` (the allow-listed `frame-ancestors` replaces it on
those responses only). If your app already sends a CSP, it is kept and this
directive is added as a second policy; if your own policy has a
`frame-ancestors` directive, add the same origins there too, since browsers
enforce both.

On the framing page:

```html
<iframe id="support-admin" src="https://support.example.com/admin/support/tickets"
        style="width:100%;border:0" title="Support administration"></iframe>
<script type="module">
    import { connectEscalatedFrame } from '@escalated-dev/escalated/embed';

    const frame = connectEscalatedFrame(document.getElementById('support-admin'), {
        origin: 'https://support.example.com',
        onNavigate: ({ route, title }) => highlightMenu(route),
    });
    frame.setTheme('light');
</script>
```

The frame sizes itself to its content and reports each page change; the
protocol is in the frontend guide.

### Session cookies in a frame

- **Same site** (`admin.example.com` framing `support.example.com`): the default
  `SameSite=Lax` session cookie works.
- **Cross-site** (different registrable domains): the session cookie must be
  sent in a third-party context. Set `SESSION_SAME_SITE=none` and
  `SESSION_SECURE_COOKIE=true`, and serve both sites over HTTPS. Browsers that
  block third-party cookies will still not keep a session in a cross-site
  frame; use the Vue + Inertia or Blade setup in that case.
- CSRF protection is unchanged.

## The admin menu: `escalated.navigation`

While embedding is on, every Inertia page shares the admin menu, and
`Escalated::adminNavigation(?$user, ?$request)` returns the same list for Blade:

```json
[
  {
    "key": "tickets",
    "label": "Tickets",
    "route": "escalated.admin.tickets.index",
    "url": "/admin/support/tickets",
    "icon": "tickets",
    "position": 20,
    "active": true,
    "badge": null,
    "plugin": null,
    "children": []
  }
]
```

It is filtered for the user: empty for anyone who fails Escalated's admin gate
(unless you replaced it), screens whose feature is off are left out (Roles
while roles & permissions is off, API tokens while the API is off, plugins
while plugins are off, newsletters while they are off), and while roles &
permissions is on each item needs the permission its route requires. Plugin
admin menu items are included, sorted by `position` with the core items. Core
`icon` keys match the frontend's `escalatedNavIcons`.

## Security

- **The admin gate stays on by default.** Your middleware runs *in addition to*
  Escalated's `escalated-admin` gate. Set `replace_admin_gate` only if your
  middleware itself restricts the routes to admins (e.g. `can:access-admin`):
  with it on, your middleware is the only check on who reaches the admin.
  Two-factor and route permissions still apply either way, and the agent panel
  keeps Escalated's gates.
- **Framing is allow-listed.** In iframe mode only the origins you list (plus
  your own) may frame the admin; nothing else, and never `*`.
- **No open `postMessage`.** The frame talks only to an allow-listed parent
  origin and ignores messages from anywhere else.

## Turning it off

Remove the `embedAdmin()` call or set `ESCALATED_EMBED=false`, then
`php artisan route:clear`. The admin returns to `/support/admin` with
Escalated's own chrome.
