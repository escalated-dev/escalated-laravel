# Requesters, metadata and ticket subjects

The agent REST API can create a ticket for a host user or a named contact and
attach the host's shipment, account, order or other models in the same call.
These operations require self-hosted mode, an agent API token, the ticket policy,
and the current merchant's tenant scope when tenancy is enabled.

## Create a complete ticket

POST /support/api/v1/tickets (or the configured API prefix):

```json
{
  "subject": "Parcel arrived damaged",
  "description": "Please arrange a replacement.",
  "requester": {"name": "Alex Recipient", "email": "alex@example.com"},
  "external_reference": "TRACK-001",
  "metadata": {"source": "merchant_portal", "order_number": "ORD-87"},
  "subjects": [
    {"type": "shipment", "id": "shp_01JXYZ", "role": "parcel"},
    {"type": "order", "id": "00087", "role": "purchase"}
  ]
}
```

Use exactly one requester form:

- {"id": 42} or {"id": "host-uuid"} resolves the configured host user model on
  its own connection. Tenant discovery and canReference must both allow it.
- {"name": "Alex", "email": "alex@example.com"} resolves a package Contact in
  the current tenant. Email is normalized; an existing nonempty contact name is
  retained. It does not create a host login or assert that the mailbox has been
  verified. No public guest credential is issued.
- Omitting requester preserves the authenticated agent as requester.

The response adds requester.kind (user/contact/guest) and requester.id to the
existing name/email fields. For a contact, id is the package contact key; for a
user it is the host key. The initial activity records the authenticated agent
who performed the operation, independently of requester identity.

metadata accepts a JSON object/array or null, bounded by
escalated.api.max_metadata_bytes (16,384 encoded bytes by default). It is stored
only as metadata; embedded tenant/requester fields never become ticket columns.
external_reference is a separate nullable string of at most 255 characters.
The host must authorize the tracking/reference association. Recipients can use
it with fresh mailbox proof in the [guest lookup flow](guest-access.md).
Host-user tickets without a guest email are not exposed by email-only matching.

Existing subject, description, priority, department_id and tags fields remain
supported. Unknown requester/subject entry keys, mixed requester forms, malformed
keys, duplicate canonical subjects, unavailable records, and excessive payloads
return validation errors before writes.

## Configure allowed subject types

```php
'ticket_subjects' => [
    'types' => [
        'shipment' => App\Models\Shipment::class,
        'order' => App\Models\Order::class,
    ],
    'max_per_ticket' => 100,
    'authorize' => null,
],
```

Lists of model classes and lists of registered morph aliases also work. Map
aliases resolve directly to their configured class; they do not require a global
morph map. Stored/public types use the model's canonical getMorphClass(), so
register the host's morph map when public aliases should also be persisted.
Alias and class inputs naming the same model/key count as a duplicate.

An empty allowlist disables API attachment of host subjects. Trusted model-based
attachSubject and syncSubjects keep their programmatic empty-allowlist behavior.
HTTP input never selects arbitrary classes from the global morph map. IDs must
be nonempty integer/string keys of at most 255 bytes; strings retain their key
type and leading zeros. Roles are nullable strings of at most 255 characters.
Positions come from array order.

The optional authorize callable receives ($actor, $subject, $ticket, $purpose)
and must return exactly true. purpose is attach or view; ticket is null during
initial prevalidation for creation, then the persisted ticket during its final
write check. Hosts can use a callable class/method in cached configuration or
bind a TicketSubjectResolver subclass. This adds object-level host policy to
mandatory tenant discovery/canReference checks. A formerly visible host object
that is no longer authorized renders as a missing subject without invoking its
presentation methods.

## Read or replace subjects

- GET /tickets/{reference} includes subjects in ticket detail.
- GET /tickets/{reference}/subjects returns the ordered complete set.
- PUT /tickets/{reference}/subjects with {"subjects": [...]} replaces that set.
  An empty array clears it. Missing subjects on PUT is an error.

Each response entry preserves type, id (the host entity key), role, title,
subtitle, url, color, icon and missing. It adds link_id (the package link key) and
position. Retained type/key pairs keep their link IDs while roles/order change.
Resolve every entry before replacement; an unavailable last entry cannot erase
or partially change the existing set. Repeated identical PUTs preserve the set.
Concurrent PUTs lock the parent ticket and commit complete sets.

The web admin attach endpoint uses the same allowlist and host visibility rules.
Programmatic syncSubjects also validates all entries before an atomic set update.

## Database, events and driver boundaries

Host requester/subject reads use each model's connection. Ticket, contact, tags,
subject links and initial activity write in one transaction on the Escalated
connection. A failed final write rolls back the whole aggregate. Tenant reference
checks run again at subject writes, including retained links.

For this aggregate path, TicketCreated runs after the complete package
transaction commits. Listeners see the final reference, contact/requester,
metadata, tags and subjects. Outer rollback suppresses the event; delayed commit
restores the captured tenant context. Ordinary existing driver creation retains
its previous event behavior. External notifications are not a transactional
outbox: after-commit listener failures can occur after the ticket is committed.
The endpoint does not provide idempotency keys or automatic retry guarantees.

Cloud and synced modes return a 422 unsupported_driver validation error for the
new fields and subject endpoints before remote requests or local writes. Ordinary
creation with none of these fields keeps the existing driver contract.
TicketDriver is unchanged; the optional CreatesAgentTickets capability lets
self-hosted custom drivers implement atomic agent creation explicitly. LocalDriver
provides the package implementation; custom subclasses may override it. The new
capability does not claim cloud synchronization of contacts or subject links.
