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
