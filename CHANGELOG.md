# Changes

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
