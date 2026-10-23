# Migration status — 2026-10-23

Upstream identity is pinned in UPSTREAM.md. This development branch adapts GPL
WordPress functionality to an independent Unlimited control plane. It is not a
production release or a functional-parity acceptance claim.

| Source capability | Disposition | Current status |
| --- | --- | --- |
| Content, files, draft themes, classic themes, previews, custom fields | Adapt GPL source | Private descriptors behind local auth/approvals; broad runtime parity pending |
| Elementor, Beaver, Breakdance, Bricks, SeedProd compile-login minting | Adapt GPL source | Imported and gated; actual builder integrations and compile automation pending |
| Native CLI | Adapt GPL source | All 131 upstream allowlist entries preserved; native SQL reads/writes and search-replace use exact local approvals; security options, identity/audit tables remain protected |
| SVG sanitizer, Alpine | Reuse | Original asset/license text preserved |
| Tailwind preview | Adapt | Locally bundled 4.1.13 with MIT notice; no browser CDN dependency |
| Hosted worker signatures and approval bypass | Replace | No vendor key accepted; local exact-operation, expiring, single-use browser approval |
| Hosted OAuth, routing, catalog | Replace | Own local auth and separate 51-tool multi-site gateway source; 52 tools including list_sites |
| Audit and operation receipts | Replace | Local user/connection-scoped receipt events; gateway scope also binds user/site/grant/tool/arguments |
| Hosted preflight, recipes, account links, promotional UI | Replace/remove service dependency | Removed from active code; upstream documentation kept only as historical attribution |
| Hosted image search, progressive skill content | Rebuild independently | Openverse adapter preserves license/creator metadata and propagates provider rate limits; four locally authored workflows replace unavailable hosted content |
| Detached jobs | Rebuild independently | Single-use local cron jobs bind original approval/site/user/connection/arguments; revoked grants cannot execute; queued/completed receipts verified |
| Hosted browser orchestration | Rebuild independently | Page-scoped builder-login minting retained; full browser-driven compile acceptance requires the actual builder environment |
| Upstream self-update | Replace | Refuses upstream update path; independent verified-release workflow pending |

Static audit records every literal hosted-domain match, every route-registration
location, HTTP call and worker-proof review location from the pinned upstream.
The source retains all 131 CLI allowlist entries; this does not mean every entry
has runtime acceptance. The current catalog contains 51 tools, 29 reads and 22
approval-gated operations. A second draft-delete route is replaced by the same
delete_draft_theme operation with the local snapshot guard. Vendor control-plane
routes are replaced rather than exposed as tools.

Verified locally: 77 PHP syntax checks; 160 isolated WordPress integration checks;
49 multi-site gateway checks using in-memory SQLite and mocked HTTP. Smoke
operations made zero outbound HTTP requests. No active-source vendor domain or
public approval-key matches remain. Root GPL and sanitizer LICENSE hashes match
the initial clone. The six imported Unlimited local control-plane files were
compared byte-for-byte to the accepted 1.0.0 ZIP before adaptation, whose SHA-256
is 2cfdfab36949046a620f5b36dbe7d2f6f2df7a82bc38bd5f733e84cd256cba9a.

Retained accepted Unlimited safeguards include 1 MiB stable reads and mutations,
bounded search/list/compile traversal, link refusal, atomic draft writes, PHP
validation and redeclaration checks, encrypted preview tokens, metadata-only
change notifications and fail-closed render checks with publish rollback. New
SVG sanitation remains functional. Legacy template field APIs and builder hooks
are retained without importing hosted authorization.

Native `/wp/v2/` reads use the read orchestration path. Extension REST endpoints
can have side effects even with GET, so they require the approval-gated path
with their actual method. A fixture reproducing that bypass is now denied before
execution; the same extension remains callable after exact browser approval.
Background execution requires functioning WordPress cron (or an external cron
trigger); a queued receipt is never treated as proof of execution.

Openverse implementation references (independent service; its limits still apply):
https://docs.openverse.org/packages/js/api_client/index.html and
https://docs.openverse.org/api/reference/authentication_and_throttling.html.
The API tool could not verify live access (HTTP 403); provider tests mock HTTP,
including HTTP 429 without retry. No live provider acceptance is claimed.

Production WordPress, existing gateways, Site registrations and accepted release
artifacts were not changed. No production deployment is authorized. Continue with
remaining builder fixtures, MySQL-specific behavior and isolated end-to-end
gateway-to-WordPress tests before production acceptance. Real builder/browser
acceptance requires an authenticated disposable site with the relevant plugins.
