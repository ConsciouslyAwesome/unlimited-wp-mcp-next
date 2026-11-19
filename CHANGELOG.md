# Changes

## 2.0.1 — 2026-11-18

- Restore the requested image/skill adapters using independently owned content and
  optional Unlimited Unsplash configuration, with Openverse fallback.
- Remove production example registry seeding; add guarded legacy-fixture cleanup.
  Align gateway version and bypass-status HTTP header with Unlimited 2.0.1.

- Add source-based connection/readiness diagnostics, consent-page warnings and
  fresh authenticated post-OAuth site verification. See CONNECTION-PARITY.md for
  status semantics, Unlimited adaptations and remaining baseline differences.
- Add source-evidenced WPVibe 1.20.5 public-name adapters to the gateway, preserving
  existing handlers, encrypted approval handoff and hidden legacy aliases. See
  MCP-PARITY.md for verified matches and unavailable hosted contracts.
- Restore administrator-browser approval bypass and bounded remembered SQL using
  Unlimited's existing local approvals, audit and connection scope. See
  APPROVAL-PARITY.md for the GPL baseline scope and unavailable Worker details.
- Restore the WPVibe 1.20.5 deferred self-update implementation under Unlimited
  names, retaining its version assertions, loopback/request handoff, updater lock,
  rollback and activation recovery, status reporting and network auto-update list.
- Discover this plugin's updates from stable ConsciouslyAwesome/Unlimited GitHub
  releases with an installable `unlimited-wp-mcp-next.zip` asset. Other plugins'
  update sources and the existing local approval flow are unchanged.

## 2.0.0 — 2026-11-02

- Pin GPL upstream at 62bc77ccd1c15faf766f527a7935fe32f1b227d3.
- Begin independent namespace and control-plane migration.
- Replace hosted UI, proof keys and updater with local Unlimited integration.
- Preserve original attribution and upstream changelog in upstream-history/.
- Retain bounded atomic file operations, safe SVG writes, redacted activity,
  encrypted previews and rollback when the publish render check is unavailable.
- Add exact-approved native SQL/search-replace, single-use cron execution,
  independent image discovery and locally authored workflow guidance.
- Retain legacy theme field APIs, the editor field sidebar and builder hooks.
- Gate extension REST requests even when their HTTP method is GET.
- Validate 189 isolated WordPress checks on source and extracted package, 52
  multi-site gateway checks, and normal activation/bootstrap checks.
- Complete a focused upstream-divergence audit: commercial-builder handlers retain
  upstream behavior apart from namespacing and local control-plane plumbing; restore
  the historical WPVibe identity in the Bricks legacy-layout compatibility warning.

Final release candidate. Production deployment remains separately prohibited
until explicit approval.
