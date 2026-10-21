# Ticket access and routing

These features are independent opt-ins for installations that store tickets locally (`self-hosted` or `synced`). Cloud-only mode rejects these options because local scopes cannot enforce access on a remote ticket store. Existing installations keep unrestricted staff visibility, no automatic account association, and the existing default SLA behavior.

Run the package migrations before enabling these options. The additive migration keeps existing tickets intact, adds nullable `account_type` / `account_id` columns with a composite index, and adds nullable SLA targets. Existing rows are not automatically assigned an account or a department.

| Config | Default | Effect |
| --- | --- | --- |
| `escalated.visibility` | `all` | Set to `department` to restrict agents to department membership. |
| `escalated.accounts.enabled` | `false` | Resolve accounts from requesters and subjects and expose account identity in the staff API. |
| `escalated.accounts.resolver` | `null` | Resolver class; alternatively bind `TicketAccountResolver` in the container. |
| `escalated.accounts.scope_agents` | `false` | Restrict an agent to the account returned by the resolver. |
| `escalated.sla.targeted` | `false` | Select SLAs by department and ticket type. |
| `escalated.tickets.require_department` | `false` | Every new ticket must have a department. |
| `escalated.tickets.default_department_id` | `null` | Department given to a new ticket created without one when a department is required. |

The corresponding environment variables are `ESCALATED_VISIBILITY`, `ESCALATED_ACCOUNTS_ENABLED`, `ESCALATED_ACCOUNT_VISIBILITY`, `ESCALATED_TARGETED_SLA`, `ESCALATED_REQUIRE_DEPARTMENT`, and `ESCALATED_DEFAULT_DEPARTMENT_ID`.

## Department visibility

```php
// config/escalated.php
'visibility' => 'department',
```

Membership comes from the existing `department_agent` pivot:

```php
$department->agents()->syncWithoutDetaching([$agent->getKey()]);
```

An agent sees tickets in any of their departments. Tickets with no department are hidden, and an agent with no departments sees no tickets. Being the assignee, requester, or follower does not override staff restrictions. Administrators retain full access within their current tenant; this never bypasses tenant isolation. Configured agent/admin gates are honored.

The Ticket global scope covers local lists, search, route binding, API and mobile queries, dashboard counts, related-ticket queries, and bulk actions. Reports explicitly apply it to raw joins, reply metrics, and satisfaction ratings as well. Bulk actions process only visible tickets in a mixed selection. Policies also check membership for already-loaded tickets, including view, update, reply, internal notes, assignment, and close.

Authenticated staff using the widget receive the same restrictions. Customers retain their existing ownership checks; guests still need a verified, expiring ticket grant. Scheduled jobs with no authenticated actor process all tickets in their tenant. Scopes resolve the current request actor at query time, including bearer-token API users, and do not cache membership across requests.

Restricted agents cannot subscribe to the shared `tickets` or `chat.queue` broadcast channels, which contain ticket details across departments/accounts. Ticket-specific subscriptions remain available subject to the ticket policy. List and queue UIs should refresh through their scoped HTTP endpoints. Queued notifications check the recipient's current visibility before delivery.

Host services can deliberately bypass only this optional scope for trusted internal work:

```php
Ticket::withoutGlobalScope(\Escalated\Laravel\Models\Scopes\TicketVisibilityScope::class);
```

Keep normal scopes on user-facing queries. This feature is an application access boundary, not database row-level security; raw host SQL and custom ticket drivers must enforce the same rules.

## Required department

```php
// config/escalated.php
'tickets' => [
    // ...
    'require_department' => true,
    'default_department_id' => 3, // optional
],
```

With `require_department` on, a new ticket created without a department gets `default_department_id`. If no default is set, or the default department no longer exists, the ticket is not created and a validation error is raised on `department_id`.

The check runs when the `Ticket` model is created, so it covers every path:

- **Web, API, mobile, widget and guest forms**: the request fails with a `department_id` validation error (a 422 JSON response, or a redirect back with errors for form posts). A department chosen in the form is kept.
- **Live chat**: starting a chat fails with the same 422 unless a default is set.
- **Inbound email**: the email is recorded as `failed` with the error and no ticket is created. Set a default department if you accept tickets by email.
- **Imports** (`ImportService`, anything run inside `ImportContext::suppress()`) are exempt and keep the department of the source record, including none.
- **Host code** calling `Ticket::create()` or a ticket driver gets an `Illuminate\Validation\ValidationException`.

Existing tickets are not changed. Staff can still clear or change a ticket's department later; the option only applies at creation. Use it with department visibility (`'visibility' => 'department'`) so no new ticket is hidden from every agent.

## Account association

An account can be any persisted host Eloquent model, including one with a UUID or string key. The association uses the model's morph alias when configured. Implement the resolver in the host application:

```php
use Escalated\Laravel\Contracts\TicketAccountResolver;
use Illuminate\Database\Eloquent\Model;

final class SupportAccountResolver implements TicketAccountResolver
{
    public function resolve(Model $model): ?Model
    {
        return match (true) {
            $model instanceof \App\Models\Company => $model,
            $model instanceof \App\Models\User => $model->company,
            $model instanceof \App\Models\Order => $model->company,
            default => null,
        };
    }
}
```

```php
'accounts' => [
    'enabled' => true,
    'resolver' => \App\Support\SupportAccountResolver::class,
    'scope_agents' => false,
],
```

New tickets resolve their requester account before creation listeners run. Agent API creation resolves the requester first, then the first subject with an account, before persisting the aggregate. Attaching a subject can fill an empty account. Existing account associations are preserved when subjects are added or removed; changing a ticket's company requires an explicit trusted host action. No account is inferred from arbitrary request input or metadata.

```php
$ticket->account()->associate($company);
$ticket->save();

$tickets = Ticket::forAccount($company)->get();
```

Staff list endpoints accept `account_type` and `account_id` together as query parameters. Both columns are compared; equal IDs from different model types do not identify the same account. Filters only narrow the actor's visibility. Account-enabled staff API resources include `account: {type, id}` or `null`; they do not serialize the entire host model.

Enable `accounts.scope_agents` to restrict each agent to the account returned by that same resolver. A missing account grants no access, including to unallocated tickets. If department visibility is also enabled, both restrictions must match. Administrators still retain access within their tenant. Existing data needs a host-managed backfill before enabling either restriction if agents should retain access to those tickets.

Automatic skill and department assignment skips agents who cannot access the ticket under the configured restrictions.

Business event ingestion, domain relationships, staff membership synchronization, and account mapping remain host responsibilities.

## Rule conditions

Escalation rules, automations, and workflows accept `ticket_type`, `account_type`, and `account_id`. Prefer the complete `account` condition to avoid matching the same ID across model types:

```php
[
    ['field' => 'ticket_type', 'operator' => 'equals', 'value' => 'incident'],
    ['field' => 'account', 'operator' => 'equals', 'value' => [
        'type' => $company->getMorphClass(),
        'id' => (string) $company->getKey(),
    ]],
]
```

Escalation/automation identity conditions use equality, like their existing department/type filters. Workflows also support `not_equals`, `is_empty`, and `is_not_empty` for `account`. Malformed account identities do not match. These fields are opt-in by adding conditions; existing rules are unchanged. Ticket types remain `question`, `problem`, `incident`, and `task`.

## Targeted SLAs

Set `escalated.sla.targeted` to `true`, then set a policy's `department_id`, `ticket_type`, or both. The admin policy endpoints accept these fields; form adapters receive `targeted`, `departments`, and `ticketTypes` props. Hosts can also create policies through the model:

```php
SlaPolicy::create([
    'name' => 'Incident response',
    'department_id' => $operations->id,
    'ticket_type' => 'incident',
    'is_active' => true,
    'is_default' => false,
    'business_hours_only' => false,
    'first_response_hours' => ['medium' => 1, 'high' => 0.5],
    'resolution_hours' => ['medium' => 8, 'high' => 4],
]);
```

At ticket creation, active matching policies are ranked: department and type, department only, type only, then the untargeted default. The lowest policy ID wins a tie. If no policy matches and no default exists, no SLA is attached. Targets are ignored while the option is off, and only untargeted defaults are selected. When a ticket changes department (through `Ticket::changeDepartment()`, which dispatches `DepartmentChanged`: the agent and admin screens, the API, bulk and macro actions, escalation rules, and workflow `set_department` actions), the policy is selected again with the same precedence, as long as `escalated.sla.enabled` and `escalated.sla.targeted` are both on. If a different policy is selected, its deadlines are anchored on the time of the change, not on `created_at`, so a ticket moved late in its life does not arrive already breached. A deadline the new policy does not set is cleared, and when no policy matches the policy and both deadlines are cleared. A first response that has already happened keeps its deadline, recorded breaches are kept, and resolved or closed tickets keep the SLA they finished under. If the same policy is selected, nothing changes. Deadlines do not change automatically when a policy is edited or a ticket's type changes; the host may call `SlaService::reselectPolicy($ticket)` to select again. Remove or retarget a department's SLA policies before deleting that department.
