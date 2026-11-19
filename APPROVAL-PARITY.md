# Approval behavior restored in local 2.0.1

Baseline: GPL WPVibe 1.20.5, commit
`62bc77ccd1c15faf766f527a7935fe32f1b227d3`.

- The per-site **Dangerously bypass approvals** setting is OFF when absent.
  It uses the baseline's administrator capability, nonce and matching logged-in
  browser-cookie checks. Application-password and Unlimited OAuth/tool execution
  contexts cannot change it. Multisite enabling requires a super administrator.
  The original settings warning, persistent administrator banner, enabler identity,
  timestamp and disable link are retained with Unlimited branding.
- Generic option writes cannot enable it. Existing CLI, SQL and content-option
  restrictions on Unlimited security state remain intact. Unlike the upstream
  generic sanitizer, disabling through a generic option update is also refused;
  this follows the requested administrator-only enable/disable requirement.
- Bypass is restricted to the GPL bypass-proof route scope: CLI approved execution
  and code snippets. The native `white_label_enable` always-ask exception remains.
  Other Unlimited tool gates, capabilities, hard refusals, file protections, write
  locks, OAuth, encryption, connection scope and receipts are unchanged. Queued
  detached jobs retain exact approval; no new queue bypass is introduced.
- Every bypassed operation records a `bypassed` audit event with its operation ID,
  user and existing connection context, followed by normal started/result receipt
  events. Failure to record the bypass event prevents execution. Setting changes
  are audited separately. Raw commands and secrets are not added to the audit log.
- Remembered SQL is an unchecked browser-approval choice, scoped to the existing
  site, user and Unlimited connection. The 1.20.5 changelog specifies **30 minutes
  or 25 statements**. The initial explicitly approved statement counts as one;
  subsequent attempts consume their allowance before execution, without retries.
- Only native `db_query_(update|insert|delete|replace):sql` classifications qualify.
  The original PHP SQL handlers are unchanged: no schema/other statement scope,
  no multisite, cross-schema, code/credential/plugin table scope, nontransactional
  tables, triggers/views/routines or custom database connection implementations.
  Code storage fingerprints and transaction rollback, protected identity tables,
  and connection-loss/no-replay checks remain in the native handlers. Statements
  that need their own approval still get exact approval even with bypass enabled.
- Without explicit bypass or a human-remembered eligible SQL session, the existing
  exact argument, site, user, connection, expiry and one-use approval checks apply.

## GPL source limits

The vendor Worker and its approval/session UI and persistence implementation are
not present in the pinned GPL repository. Its precise session key, UI details,
statement accounting edge cases and cross-verb matching cannot be independently
reproduced from that source. The local implementation uses the documented SQL
group, 30-minute/25-statement ceiling, counts the first approved statement, and
never extends a session automatically. Unlimited's existing local browser gate
and connection-bound storage replace that unavailable hosted implementation.
Vendor signing keys/proofs are deliberately not restored.

Focused checks (in-memory doubles; no network or live WordPress database):

```
php tests/approvals.php
php tests/approvals.php sql
```
