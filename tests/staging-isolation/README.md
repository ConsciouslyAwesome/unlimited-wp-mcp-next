# Staging isolation helper, excluded from releases

This fixed-purpose WordPress test plugin operates only when both `home` and
`siteurl` use HTTPS on `booking.fractalillumination.com`, with an administrator,
single-site WordPress, Amelia 9.10, Unlimited's encrypted store and InnoDB tables.
Installing or activating it alone does not isolate anything. Its Tools page
requires a WordPress nonce for the separate isolation action.

Read-only inspection on November 24, 2026 found two Google and two Outlook
connections, four Zoom-linked users, 41 enabled email notifications, 10 enabled
SMS notifications and 39 enabled WhatsApp notifications. Amelia SMS was signed in;
mail used `wp_mail`. The staging dashboard also showed configured Post SMTP.
These findings do not establish whether the connections point to production or
sandbox accounts, so mutation testing must treat them as real until isolated.

## Exact changes

1. Save and verify an encrypted backup in a non-autoloaded staging option using
   Unlimited's existing AES-GCM store. It contains Amelia settings, active plugin
   names, calendar/push connection rows, provider integration fields and notification
   statuses. It is never exported, logged or returned to the browser.
2. Enable WordPress mail/HTTP/cron guards. If a later step fails, keep these guards
   and the backup while rolling back the transactional configuration changes.
3. Remove local Google, Outlook and mobile push connection records and clear local
   provider calendar/Zoom links. Do not call disconnect/revoke APIs.
4. Disable all Amelia notification templates, SMS sign-in, WhatsApp credentials,
   webhooks and external integration enable flags in this staging database.
5. Pause other normal plugins by retaining only Amelia, Unlimited Next and this helper
   in `active_plugins`. Preserve the original list for explicit recovery. Do not run
   third-party deactivation hooks that might contact external providers.
6. Leave appointments, customers, schedules, buffers, slot length and resources unchanged.

The Tools page reports only connection/notification counts, nonsecret policy flags,
active plugin names and backup presence. A failed count query raises an error rather
than displaying a false zero. Verify all counts are zero and guards are enabled on
a fresh request before any appointment mutation test.

## Recovery and limits

The separate restore action recovers the saved connection rows, integration fields,
notification statuses, settings and active plugin list in a transaction. Only after
commit does it turn off the guards. The backup remains encrypted. Restoring resumes
the original external integrations, so do not perform recovery automatically after
testing. Recovery replaces any synthetic calendar/push connections created after
isolation; test data in those connection tables must be disposable.

WordPress HTTP filtering does not block native cURL or arbitrary socket libraries.
Removing cloned credentials, disabling notification channels and pausing unrelated
plugins are essential, not optional replacements for that filter. This helper is
not a host firewall and does not certify the behavior of other processes, arbitrary
must-use plugins or direct external requests. Dedicated sandbox provider accounts
are still required for live Google/Outlook/Zoom and message-delivery acceptance.

No new generic PHP evaluator, SQL endpoint or MCP authorization bypass is added.
Never ship this helper in the production plugin ZIP. Never use the restore action
without a deliberate decision to resume staging connections.

## Draft critique and refinements

An HTTP-only blocker was insufficient because the inspected Amelia SMS service
uses direct cURL. The helper therefore isolates credentials/channels as well.
An unencrypted database backup would retain cloned secrets as additional plaintext;
the helper instead uses the existing authenticated encryption implementation.
Treating failed count queries as zero would hide incomplete isolation; the status
reader now rejects missing or failed count results.

Policy tests cover hostname restrictions and preservation of scheduling settings.
Orchestration doubles use the real encrypted store to cover backup confidentiality,
explicit recovery, transactional rollback, InnoDB requirements and fail-closed guards.
These tests do not prove that a deployed staging environment is isolated.
