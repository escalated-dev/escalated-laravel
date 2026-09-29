# Private attachment delivery

New uploads default to the host's `local` disk and request private visibility. Configure
`ESCALATED_ATTACHMENTS_DISK` (or `escalated.storage.disk`) to use another private disk. Its root
must not be served by the web server, a public storage link, CDN or public bucket policy.

Serialized attachment URLs point to `escalated.attachments.download`, expire after ten minutes,
and require current ticket access. `escalated.storage.download_ttl_minutes` can be set from 1
to 60 minutes. Expiry/signature checks also apply to agents. Download responses use attachment
disposition, `private, no-store`, and `nosniff`; disk names and storage paths are no longer
serialized. Refresh the ticket to obtain a new link after expiry.

Browser downloads use the host session. Set `escalated.storage.download_guard` when that session
uses a nondefault authentication guard; configured agent/admin gates remain subject to the ticket
policy. API clients send their Escalated bearer token with the
download request; customer tokens grant only requester access, including when their owner also
has an agent role. Internal-note attachments require agent/admin access. Missing/deleted parents
and files cannot be downloaded. The route is registered independently of the Inertia UI. Hosts
that disable all package routes must register the named route with the same signature and
authorization checks.

Existing guest web/mobile ticket controllers issue short-lived download capabilities only after
resolving a valid guest ticket token. These capabilities bind the attachment to that ticket and
exclude internal notes; rotating the guest token revokes outstanding downloads. This change does
not add guest email verification or expiry to the ticket token itself; those are separate guest
access requirements.

## Upgrade existing public files

Changing URLs does not remove files already served publicly. Before enabling merchant access:

1. Run the package migrations to create the recovery journal. Configure a private destination
   disk and verify its root/bucket cannot be read publicly. Stop attachment writes/deletions
   during migration and run only one migration command at a time.
2. Run `php artisan escalated:attachments:privatize --from=public --to=local` to review the count.
   Without `--apply`, the command changes nothing.
3. Run the same command with `--apply`. It streams each file to a new private path, verifies
   SHA-256, updates the database reference, then removes the public original when no other
   attachment references it. Destination paths use new random UUIDs. A database journal records
   each move before bytes are written, and is cleared only after public-file cleanup succeeds.
4. Resolve every nonzero exit before allowing access. A failed copy leaves the original reference
   intact. An interruption or failed public-file deletion retains a recovery entry. Rerun with
   the same source and destination to remove unused private copies and retry public-file cleanup,
   including for records already moved. Pending cleanup causes a nonzero exit. Keep the journal
   until recovery completes; rolling its migration back while entries remain is refused.
   Concurrent attachment edits are refused and leave a `conflict` journal entry for manual
   reconciliation of both paths; this is why writes and deletions must be paused first.
   Clear any CDN copies and verify an old public URL no longer returns the file.

Repeat for each source disk. Imported attachments use `escalated.attachments.disk` to identify
where their existing files live; imports do not copy bytes automatically. No migration command is
run automatically on package installation or deployment.
