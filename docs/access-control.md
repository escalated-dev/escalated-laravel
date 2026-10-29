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

