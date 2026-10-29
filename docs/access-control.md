# Access control: roles & permissions, two-factor, SSO

Escalated's roles & permissions, two-factor authentication and single sign-on
are **install-level switches**, off by default
([ADR 2026-10-29](https://github.com/escalated-dev/escalated-developer-context/blob/main/decisions/2026-10-29-configurable-access-features.md)).
While a switch is off its admin screens and routes answer 404 and nothing is
enforced; the host's `escalated-admin` / `escalated-agent` gates decide who may
do what, exactly as before. While it is on, the feature is enforced.

The switches are read from config only. No admin screen or stored setting
changes them.

| Feature | Config key | Env var |
|---|---|---|
| Roles & permissions | `escalated.access.roles_permissions` | `ESCALATED_ROLES_PERMISSIONS` |
| Two-factor authentication | `escalated.access.two_factor` | `ESCALATED_TWO_FACTOR` |
| Single sign-on | `escalated.access.sso` | `ESCALATED_SSO` |

## Roles & permissions

### What is enforced

With `ESCALATED_ROLES_PERMISSIONS=true`, every admin, agent and staff API route
needs a permission **in addition to** the host gate for that area. A user's
permissions are the union of their Escalated roles' permissions. Nothing else
grants one: passing the `escalated-admin` gate or having `is_admin` set gives
no permissions by itself. Give users who should have everything the `admin`
role.

- The route to permission map is `src/Support/RoutePermissions.php`. A route
  missing from it is refused, and a test checks every admin, agent and staff
  API route is in it.
- A status change needs `ticket.edit`, plus `ticket.close` when the new status
  is `resolved`, `closed` or `reopened`.
- Bulk actions need the permission of the action: `assign` needs
  `ticket.assign`, `delete` needs `ticket.delete`, the rest `ticket.edit`.
- Applying a macro needs `macro.view` and `ticket.edit`.
- API tokens act as their owner, so the staff API checks the owner's
  permissions as well as the token's abilities.
- The newsletter screens check `newsletters.manage` and `newsletters.send` only
  while the switch is on.
- Your own two-factor settings need no permission.
- Routes that plugins register are the plugin's to guard.

Failing a permission answers 403.

### Seeded roles

`PermissionSeeder` creates the permissions and three system roles:

| Role | Permissions |
|---|---|
| `admin` | all |
| `agent` | `ticket.*`, `reply.*`, `kb.view`, `report.view`, `macro.view`, `macro.create`, `tag.view`, `custom_field.view`, `audit.view` |
| `light_agent` | `ticket.view`, `reply.create`, `reply.create_internal`, `kb.view`, `macro.view`, `tag.view` |

Custom roles are made in **Admin > Roles**.

### Giving users roles

- From the command line:
  `php artisan escalated:roles:assign you@example.com admin` (email or ID,
  then the role slug).
- From the admin: `PUT {prefix}/admin/users/{user}/roles` with `role_ids`
  (needs `role.manage`; replaces the user's roles). You cannot remove your own
  `role.manage`. While the switch is on, the users page receives each user's
  `roles` (slugs) and a `roles` list of `{id, name, slug}`.

### Turning it on

1. Seed the permissions:
   `php artisan db:seed --class="Escalated\Laravel\Database\Seeders\PermissionSeeder"`.
2. Give every staff user a role, starting with yourself:
   `php artisan escalated:roles:assign you@example.com admin`.
3. Set `ESCALATED_ROLES_PERMISSIONS=true` (and `php artisan config:cache` if
   you cache config).

Staff without a role can still reach their personal screens and nothing else.

### Shared props

Every page gets `escalated.features.roles_permissions` (boolean) and
`escalated.permissions`: the user's slugs while on, `['*']` for staff while
off, `[]` for everyone else.

## Two-factor authentication

### What is enforced

With `ESCALATED_TWO_FACTOR=true`:

- **Enrolment.** Every staff user (agents too, not only admins) can set up
  TOTP at `{prefix}/admin/settings/two-factor` (`Admin/Settings/TwoFactor`):
  `setup` issues a 160-bit secret, an `otpauth://` URI and eight recovery
  codes; `confirm` takes a 6-digit code; `disable` removes the enrolment.
  Secrets and recovery codes are stored encrypted. TOTP is RFC 6238 (SHA-1,
  6 digits, 30 s) via `pragmarx/google2fa`, accepting one step of clock drift.
- **Challenge.** Escalated does not own your login form. After the host signs
  someone in, a staff user with a confirmed enrolment who opens any admin or
  agent route is redirected to `GET {prefix}/two-factor/challenge`, which
  renders the shared `Auth/TwoFactorChallenge` page (props: `action`,
  `error`). It posts `code` or `recovery_code` to
  `POST {prefix}/two-factor/challenge`. Success regenerates the session and
  returns to the page they asked for. JSON requests get 403 with
  `two_factor_required: true` instead of a redirect.
- Every login and logout (Laravel's `Login` / `Logout` events) clears the
  mark, so each new login is challenged. The mark is tied to the user ID.
- A code works once: its time step is recorded and that step or any earlier
  one is refused. Recovery codes are removed when used.
- The challenge is throttled to 5 attempts a minute per user.
- **Required.** `ESCALATED_TWO_FACTOR_REQUIRED=true`, or the
  `two_factor_required` setting (Admin > Settings; the setting wins once
  saved), sends staff without an enrolment to the enrolment page, and they can
  use nothing else until they enrol. Shared prop `escalated.two_factor_required`
  is true for a user in that position.
- **Lost device.** `php artisan escalated:two-factor:reset you@example.com`
  removes a user's enrolment.
- The token API is not session-based and is not challenged.

While the switch is off, the enrolment and challenge routes answer 404 and
nobody is challenged. Enrolments are kept.

### Turning it on

1. Run `php artisan migrate` (adds `two_factor.last_used_step`).
2. Set `ESCALATED_TWO_FACTOR=true`. Anyone who enrolled while it was not
   enforced is challenged from their next visit.
3. Optionally require it, after telling your staff.

## Single sign-on

### What is enforced

With `ESCALATED_SSO=true`, users sign in at `GET {prefix}/sso/login` through
the provider chosen in **Admin > Settings > SSO** (`sso_provider`: `saml`,
`oauth` or `jwt`). With `none`, and for any provider other than the chosen
one, the sign-in routes answer 404.

| Route | Name | Use |
|---|---|---|
| `GET sso/login` | `escalated.sso.login` | Start sign-in |
| `GET sso/saml/metadata` | `escalated.sso.saml.metadata` | SP metadata; its URL is the default SP entity ID |
| `POST sso/saml/acs` | `escalated.sso.saml.acs` | SAML assertion consumer (HTTP-POST) |
| `GET sso/oauth/callback` | `escalated.sso.oauth.callback` | OAuth 2 / OIDC redirect URI |
| `GET\|POST sso/jwt/callback` | `escalated.sso.jwt.callback` | JWT hand-off (`jwt` parameter) |

The SSO settings page receives these as `endpoints` to give the IdP.

**SAML** (`onelogin/php-saml`, strict). Refused unless: the IdP certificate is
configured; the **assertion** is signed by it (a signed Response alone is not
enough; `KeyInfo` keys are ignored); there is exactly one assertion and it is
not encrypted; `Issuer` is `sso_entity_id`; `Audience` includes the SP entity
ID; `Destination` and the bearer `Recipient` are the ACS URL (as configured,
not as forwarded by a proxy); the validity windows hold (php-saml allows 180
seconds of drift); `InResponseTo` names an AuthnRequest this install issued in
the last 10 minutes, used once (IdP-initiated responses only with
`escalated.sso.allow_idp_initiated`); and the assertion ID has not been used
before. Request and assertion IDs are kept in `escalated_sso_message_ids`,
server-side, because the IdP's cross-site POST does not carry a SameSite=Lax
session cookie.

**OAuth 2 / OIDC** (`league/oauth2-client`). Authorization-code flow with a
single-use `state` in the session and PKCE `S256`. The code is exchanged at
the token URL server to server and the profile read from the userinfo URL. A
profile with `email_verified: false` is refused.

**JWT** (`firebase/php-jwt`). The IdP sends the user back to the callback with
`jwt`. The algorithm is pinned to `sso_jwt_algorithm` (HS* with the secret,
RS* with the public key in the same field), so `alg: none` and algorithm
switching fail. `exp`, `aud` (= `escalated.sso.jwt_audience`, default
`app.url`) and `jti` are required; a `jti` works once.

### Users and roles

- The email comes from `sso_attr_email` (SAML falls back to an email-shaped
  NameID), the name from `sso_attr_name`, the role from `sso_attr_role`.
- The email is matched to a host user case-insensitively. An unknown email is
  refused unless `escalated.sso.create_users` is on, which creates the user
  with an unusable random password.
- With roles & permissions on and `escalated.sso.sync_roles` on, a role value
  naming an Escalated role slug replaces the user's roles; unknown values
  change nothing. SSO never changes the host's admin or agent gates.
- The user is signed in on `escalated.sso.guard` (default guard), the session
  is regenerated, and they land on `escalated.sso.redirect_to` or their
  dashboard. With two-factor on, they still pass the challenge.
- Refusals answer 403 with a generic message; the reason is logged.

Escalated does not switch off the host's password login.

### Turning it on

1. Run `php artisan migrate` (creates `escalated_sso_message_ids`).
2. Set `ESCALATED_SSO=true`, choose the provider in Admin > Settings > SSO and
   give the IdP the endpoints shown there. SAML needs the IdP certificate.
3. Decide on `create_users` and `sync_roles`.

