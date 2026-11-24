# Amelia native appointment adapter (development)

Status: implementation draft, not accepted for release. Production is untouched.
Baseline: 2.0.1, commit e66e336e5b401e46c4d399a104ecf69f6970a21b.
Inspected staging: booking.fractalillumination.com, Amelia 9.10, PHP 8.3.37,
WordPress 7.1.5. Inspection date: November 24, 2026.

## Selected method

Use Amelia's installed, licence-controlled command bus and its native current-user
services. Construct only fixed command classes. Do not instantiate handlers or
override their authorization, licence mappings, service registrations or credentials.
The adapter's authenticated MCP boundary replaces the browser transport boundary;
native command authorization and the local exact-operation approval remain required.
No AJAX proxy, arbitrary endpoint or PHP evaluator is added.

The external API remains an alternative, not implemented here. No API credentials
are required or returned. No Amelia source code is redistributed.

Observed native flow:

1. `Infrastructure/ContainerConfig/container.php` assembles the current-user,
   repository, command bus and event bus services.
2. `command.bus.php` obtains handlers through `Licence::getCommands` and retains
   logging and locking middleware.
3. Commands receive the native permission and user services, as in `Controller`.
4. `GetAppointmentCommand` authorizes lookup before repository access.
5. `UpdateAppointmentCommand` preserves calendar identifiers, updates relationships
   and payment dates, and returns the event payload.
6. `UpdateAppointmentController` emits `AppointmentEdited` on success. The adapter
   emits that same event once; it never independently sends notifications.
7. `AppointmentEditedEventHandler` invokes integration, calendar employee-change,
   notification, waiting list and webhook services. Invocation does not verify delivery.
8. Notes-only edits use `UpdateAppointmentNoteCommand`, with no appointment-edited
   event, matching the narrower native operation.

The time-only handler was rejected as the default path because it does not provide
the requested notification control. Source inspection also showed that the full
update handler performs an availability check after some update work. The adapter
therefore checks availability before dispatch, including after approval under the
Unlimited write lock. Native validation still runs. This lock serializes Unlimited
writes; it does not lock out concurrent edits from Amelia's own UI. Native concurrent
booking guarantees require staging acceptance; cross-channel atomicity is unproven.

## Contract

All gateway extensions require `site` (registered ID or unique alias), resolved
through the existing per-user registry and credential binding. Native WordPress
registrations are conditional on the exact inspected Amelia version, 9.10.
Other installations do not receive the native descriptors. The gateway is multi-site
and advertises optional tools globally; an unsupported target rejects them.

| Tool | Behavior |
| --- | --- |
| `amelia_get_appointment` | Read sanitized appointment, booking/customer IDs, duration, buffers and state digest |
| `amelia_preview_appointment` | Check state and native availability without mutation |
| `amelia_reschedule_appointment` | Change time through native full update and event processing |
| `amelia_update_appointment` | Change time and/or internal notes; notes alone use the dedicated command |

Example: read appointment 102, retain its `expected_state`, then preview:

```json
{
  "site": "divi-staging",
  "appointment_id": 102,
  "expected_state": "<64-character digest from lookup>",
  "new_start": "2026-12-07 09:00",
  "timezone": "America/New_York",
  "notify_participants": true
}
```

Use `amelia_preview_appointment` as the dry run. Then call reschedule with the same
arguments. Review the WordPress approval, and repeat with its `approval_id`.
The preview shows all affected booking/customer IDs, including group participants.
The approval binds those exact arguments, site, connection and expected state.
No appointment is canceled, recreated or reassigned by these tools.

Dates are strict local wall times plus IANA timezone identifiers. DST gaps and
folds are rejected. Native datetime objects supply the Amelia display timezone;
repository factories perform UTC conversion. No fixed-offset arithmetic is used
for site conversion. Half-hour DST and fractional timezones have guard tests.

Return statuses distinguish verified stored appointment changes from unverified
calendar, notification and deferred processing. Writes return the receipt ID,
including uncertain outcomes. Never retry after uncertainty without inspecting
both the receipt and the current appointment. Preserve the original approval ID
for receipt lookup; a host termination may prevent a response altogether.

## Security and preservation

- Require WordPress `manage_options` and native appointment read/write permissions.
- Retrieve the authoritative appointment, retaining its original identity and bookings.
- Bind a digest of native state to preview/approval; do not return raw customer,
  payment, calendar or credential payloads.
- Run availability on a separate native entity retaining its existing ID.
- Recheck state and availability under the existing write lock immediately before dispatch.
- Preserve calendar identifiers, customer/booking identities and payment relationships.
- Verify time, notes, identifiers and relationship invariants after dispatch and events.
- Treat exceptions or failed verification after dispatch as uncertain, never retry automatically.
- Keep approval payloads encrypted; audit statuses contain no appointment/customer payload.
- Do not mutate the database directly or change any scheduling settings.

## Draft critique and revisions

Initial idea: use `UpdateAppointmentTimeCommand` alone. Critique: this omits the
full controller event workflow and cannot reliably honor notification settings.
Revision: use full native update plus the success event, and use the dedicated
notes command for notes-only patches.

Initial notification assumption: `notifyParticipants=false` means no messages.
Critique: `AppointmentNotificationService::sendRescheduledNotifications` gates
customer notifications with that flag, while provider notifications are independent;
the application service can also send push notifications. Revision: fail before
mutation when false is requested. Do not suppress native listeners globally.

## Acceptance checklist and rubric

An unchecked item is pending, not an implied pass. Release requires every applicable
category to pass with recorded execution evidence; mocks do not establish live behavior.

- [x] Inspect native container, command authorization, dispatcher, controller and event flow.
- [x] Establish unchanged gateway baseline (124 tests passed locally).
- [ ] Run new PHP guard tests and existing standalone PHP regressions in Actions.
- [ ] Complete native command and approval fixture tests.
- [ ] Verify staging blocks real email, SMS, WhatsApp, push, external HTTP and calendar writes.
- [ ] Run synthetic appointment creation, preview, approval and rescheduling over actual MCP HTTP.
- [ ] Verify buffers, hourly slot grid, resources, employee days off and external busy calendars.
- [ ] Verify payment, package, group, calendar and Zoom relationships after native update.
- [ ] Verify notification-on and notification-off requirements across every configured channel.
- [ ] Exercise concurrent edits and partial failures, reconcile receipts without replay.
- [ ] Run the complete existing WordPress regression suite and package acceptance.
- [ ] Build a release ZIP and SHA-256 only after acceptance, then request release approval.

| Category | Required evidence |
| --- | --- |
| Authorization | Wrong user/site, expired/spent approval and state changes rejected before mutation |
| Scheduling | Native schedule/resource/calendar conflicts and DST cases verified |
| Preservation | Appointment, bookings, payments, packages and external IDs verified |
| Side effects | Native processing once, no real recipients or production event updates on staging |
| Recovery | Uncertain operation inspected and reconciled without duplicate effects |
| Release | All applicable tests pass; unsupported features explicit; user approves release |

## Current limitations

This is not release-ready. Employee/location changes, service changes and individual
booking reassignment are not exposed. `notify_participants=false` is unsupported.
Use the preview tool rather than a `dry_run` parameter. Package/recurrence/payment
variants and external calendars require live acceptance. Only inspected 9.10 is
supported; class presence is a compatibility check, not a claim that a licensed
command can execute. The installed command map and native permissions remain final.
No production mutations, deployment or merge are authorized by this development work.
