# Migration and upstream-divergence status — 2026-11-01

Upstream identity is pinned in UPSTREAM.md. This development branch adapts GPL
WordPress functionality to an independent Unlimited control plane. The focused
audit compares the current branch to exact upstream commit
`62bc77ccd1c15faf766f527a7935fe32f1b227d3`. It does not treat independent
commercial-builder certification as part of the fork objective.

## Meaningful divergence categories

| Category | File/capability families | Why it remains |
| --- | --- | --- |
| Required hosted-service/control-plane replacement | Plugin bootstrap; removal of vendor OAuth, relay/ping, signed worker proof, approval bypass, hosted connection/status/preflight, dashboard/recipe/review/account UI, self-update and vendor uninstall flows; `includes/local-auth.php`, `local-capabilities.php`, `local-mcp.php`, `local-store.php`, `local-jobs.php`, `local-resources.php`, `local-tools.php`; `gateway/`; local workflows | Replaces the WPVibe-hosted relay, callbacks, proof keys, catalog, account/quota surface, progressive skill content, detached execution and image proxy with Unlimited-owned infrastructure. |
| Required Unlimited integration/safety plumbing | `UWNext_*` namespace and private route registry; exact expiring single-use approvals; user/connection receipts; protected SQL/search-replace; bounded file reads/writes/search/list/compile; link refusal; atomic writes; PHP/SVG checks; encrypted previews; fail-closed render/publish handling; locally bundled Tailwind preview runtime | Prevents collisions with an installed upstream plugin and retains the accepted Unlimited safety model at the new trust boundary. These are intentional differences, not new end-user capabilities. |
| Test/diagnostic/supporting changes | `tests/`, `gateway/tests/`, provenance and migration documents, regenerated catalogs, historical upstream documentation, error classification and bounded Openverse diagnostics | Proves the replacement boundary, preserves attribution and makes failures inspectable without exposing raw transport details. These do not independently re-certify upstream commercial builders. |
| Unnecessary or accidental divergence | One historical Bricks compatibility warning had mechanically changed “older WPVibe plugin” to “older UWNext plugin”; stale documentation required broad commercial-builder parity beyond the original objective | The warning is restored to the accurate upstream identity and the stale scope language is removed. No other runtime divergence was found that was both unrelated and safe to remove. |

The Elementor, Beaver, Bricks and Breakdance handler bodies were compared against
their upstream files after normalizing only the fork namespace, text domain,
route namespace, private route wrapper and attribution header. Their operational
logic remains upstream-derived. No builder algorithm was reimplemented for this
audit, and Lite/free editions are not evidence for commercial editions.

| Source capability | Disposition | Current status |
| --- | --- | --- |
| Content, files, draft themes, classic themes, previews, custom fields | Adapt GPL source | Private descriptors behind local auth/approvals; scoped staging evidence exists |
| Elementor, Beaver, Breakdance, Bricks, SeedProd compile-login minting | Preserve GPL source | Upstream handlers retained behind the local route/approval boundary; no separate commercial certification required for the fork objective |
| Native CLI | Adapt GPL source | All 131 upstream allowlist entries preserved; native SQL reads/writes and search-replace use exact local approvals; security options, identity/audit tables remain protected |
| SVG sanitizer, Alpine | Reuse | Original asset/license text preserved |
| Tailwind preview | Adapt | Locally bundled 4.1.13 with MIT notice; no browser CDN dependency |
| Hosted worker signatures and approval bypass | Replace | No vendor key accepted; local exact-operation, expiring, single-use browser approval |
| Hosted OAuth, routing, catalog | Replace | Own local auth and separate 51-tool multi-site gateway source; 52 tools including list_sites |
| Audit and operation receipts | Replace | Local user/connection-scoped receipt events; gateway scope also binds user/site/grant/tool/arguments |
| Hosted preflight, recipes, account links, promotional UI | Replace/remove service dependency | Removed from active code; upstream documentation kept only as historical attribution |
| Hosted image search, progressive skill content | Rebuild independently | Openverse adapter preserves license/creator metadata and propagates provider rate limits; four locally authored workflows replace unavailable hosted content |
| Detached jobs | Rebuild independently | Single-use local cron jobs bind original approval/site/user/connection/arguments; revoked grants cannot execute; queued/completed receipts verified |
| Hosted browser orchestration | Replace boundary only | Page-scoped builder-login minting retained behind Unlimited approvals; upstream builder behavior is not reimplemented |
| Upstream self-update | Replace | Refuses the upstream update path; packages are produced by the independent verified-release workflow |

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
Provider tests cover bounded transient retries and terminal HTTP 429 behavior.
The 2026-11-01 staging search timeout matches WordPress/openverse issue #5649:
static/stats connectivity succeeds while uncached search requests return no bytes.
It is an upstream external-dependency exception, not grounds for adapter redesign.

Production WordPress, existing production gateways, Site registrations and
accepted production artifacts were not changed. No production deployment is
authorized. Commercial-builder certification is outside the closing objective;
the fork preserves those upstream handlers and does not claim independent parity.

2026-10-23 staging findings: exact REST draft creation and content_edit completed on Divi 4.27.9/MariaDB 10.6.28; browser preview and existing-content visual editor verified. Constant SQL SELECT and single-use background read completed with receipts. Image-provider search returned image_provider_unavailable; live image search remains pending. Gateway approval validation now requires the Next admin page, matching actual staging responses; legacy-product approval URLs are refused. Gateway tests: 51 passed. Production unchanged.

2026-10-23 private staging bridge: exact draft edit, readback and receipt completed through the actual private Site namespace; constant SQL SELECT and single-use detached read also completed. Image search still returns transport unavailable. A narrow diagnostic now returns an allowlisted failure category without raw transport messages, new retries, identity changes or TLS relaxation. Source checks: 167 passed; extracted package verification pending. Production unchanged.

2026-10-25 user-requested image retry update supersedes the earlier no-retry adapter: at most three safe GET attempts, 250/500 ms backoff, selected transient timeout/reset failures and HTTP 429/500/502/503/504 only. Ten-second per-attempt timeout, 512 KiB bound, SSL verification, no redirects and JSON Accept remain. Original WordPress codes and sanitized messages plus attempt timings are exposed on failure and after recovery. Source validation: 189 checks; source/package/staging evidence is maintained in the parent workspace. Production unchanged.
