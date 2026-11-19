# Connection and preflight parity — local Unlimited WP MCP Next 2.0.1

Baseline: GPL WPVibe **1.20.5**, pinned commit
`62bc77ccd1c15faf766f527a7935fe32f1b227d3`. Evidence reviewed:
`class-wpvibe-connection-check.php`, `class-wpvibe-connection-status.php`,
`class-wpvibe-auth-diagnostics.php`, `class-wpvibe-authorize-notice.php`,
`class-wpvibe-app-password-policy.php`, `class-wpvibe-ping.php` and
`assets/js/authorize-preflight.js`. No current/later WPVibe catalog or proprietary
Worker implementation is treated as baseline evidence.

## Restored behavior

- Readiness is separate from authorization and actual authenticated access.
  The gateway checks public HTTPS, REST JSON/namespaces, this installation's
  Unlimited readiness endpoint, OAuth availability and Authorization forwarding
  before issuing an authorization link.
- A fresh 64-hex challenge binds the readiness response to the current request.
  A wrong site URL, cached response, mismatched challenge, redirect, missing
  namespace, HTML/maintenance page or transport failure cannot claim readiness.
- HTTP 401 with a Basic challenge is identified as a staging/password gate,
  rather than misreported as a rejected WordPress account credential.
- REST 401/403 JSON is distinguished from HTML/firewall interception. A restricted
  anonymous REST index can report **limited** while the actual OAuth readiness
  route works. Provider markers are labelled possible interference, not a proven
  security-plugin diagnosis. Only bounded status/marker information is retained;
  raw response bodies, credentials and tokens are not stored in diagnostics.
- Authorization pages show local HTTPS, encryption, administrator/multisite,
  Application Password and visible Basic Auth warnings. The existing browser
  login, nonce, consent form and authorization decision are not replaced.
- The WordPress **Test local readiness** action now reports local checks and a
  bounded HTTPS REST loopback, and requires administrator capability. It does not
  claim the gateway reached the site. Local connection rows show a recent read
  served, awaiting verification or revoked, rather than treating activity alone
  as proof of gateway receipt.
- The gateway verifies a fresh `site_info` after OAuth: successful HTTP and MCP
  result, matching response ID, matching canonical site URL and account capability
  evidence are required. A site-info tool call also updates read observations.
- Gateway UI and `list_sites` verification metadata distinguish **connected**,
  **limited**, **failing**, **awaiting_confirmation**, and **stored_unverified**.
  An explicit **Check connection** form reruns readiness and the authenticated
  read without changing the grant, unless the existing refresh flow is needed.
- When consent succeeds but the read fails, the gateway says **Authorized;
  verification failed**, retains the site's encrypted tokens, supplies recovery
  guidance and a check-again button. A failed one-use token exchange instead
  explains that authorization was not completed and requires a fresh link.
- Observations bind to registry user, site ID, canonical URL and OAuth credential
  generation. Older checks cannot replace newer observations. Read verification
  expires after five minutes; an authorization link's pending indication expires
  with Unlimited's existing ten-minute OAuth flow. A changed URL, account or grant
  cannot reuse another connection's status. A cloned site's mismatched identity
  and stale MCP response ID fail verification.

## Unlimited adaptations

- Only the existing registry URL determines network destinations. No discovery
  response may redirect tokens or supply a new issuer/token host. Existing OAuth,
  PKCE, state consumption, issuer/resource binding, AES-GCM encryption, refresh and
  multi-site routing remain authoritative. HTTPS checks do not relax certificates,
  follow redirects, send staging passwords or install an authentication fallback.
- A non-secret `Unlimited-Loopback` nonce tests Authorization forwarding. Its
  presence proves header transport only, not valid credentials. The public
  `/unlimited-wp-mcp-next/v1/connection/preflight` response exposes existing public
  OAuth metadata, installation URL and booleans, with no-store headers. It neither
  creates a grant nor authorizes execution.
- Disabled Application Passwords are advisory for OAuth. Their account availability
  is still reported for direct Application Password clients, but no vendor policy
  override or automatic enablement is restored. Limited administrator permissions
  are warnings; the existing OAuth `read` capability boundary is not redesigned.
  Actual tools continue to enforce their own capabilities.
- The existing `connection_status` field keeps its stored-credential semantics,
  and `connect_site` remains idempotent for a stored connection. A separate fresh
  `verification` object prevents that field from being mistaken for current health.
  `remove_site` still removes local authorization and attempts revocation.
- `0002_connection_observations.sql` adds status evidence in the same existing D1
  binding, scoped to existing grants. No hosted database migration has been run.
  WordPress stores only site/user/connection-bound recent read-served metadata;
  the gateway independently verifies receipt. Existing receipts are unchanged.

## Hosted-only behavior that remains unverifiable

The GPL repository contains the plugin-side report/proof contracts but not the
vendor Worker's preflight execution, relay selection, account health aggregation,
telemetry ingestion or proprietary client UI. Those implementations cannot be
reproduced exactly. This pass does not restore vendor hosts, IP allowlists,
signature keys, signed-worker observations, quota/account state, fallback headers,
beacons or later-version features. The same useful reachability and read-verification
outcomes are supplied by the existing Unlimited gateway instead.

## Remaining visible differences and limits

- Readiness warnings appear on Unlimited's OAuth consent page and gateway check
  results, not WordPress core's Application Password approval page. There is no
  vendor beacon or core application-password mint-path interception.
- The gateway does not guess which exact security, CDN or caching rule caused a
  failure. It reports observable HTTP/JSON/header evidence, possible known vendor
  markers and concrete next steps. It cannot provide a vendor relay IP allowlist.
- Verification is a point-in-time authenticated read, not continuous monitoring or
  proof that every write/tool capability works. Saved credentials can remain while
  verification is failing; the UI states this explicitly. No write test is run.
- The baseline's detailed Application Password rejection enrichment (empty stored
  password list versus hash mismatch, cache/database drop-in facts) is not installed
  globally on WordPress authentication. Unlimited OAuth does not mint Application
  Passwords; direct Application Password callers retain core's existing errors.
  Their availability is reported, but this is not full direct-credential diagnostic
  parity and no unavailable credential is claimed to have been tested.
- The final routing repair preserves clean public HTTPS installation paths and
  supports pretty and front-controller `rest_route` REST forms. The detected
  same-origin installation/ancestor-home profile is encrypted with the flow and
  grant; admin URLs and approval paths retain the canonical WordPress directory.
  Existing root-site encrypted data remains compatible. Ports, traversal and
  encoded-path tricks remain refused. See the final acceptance report.
- Existing installations without the new readiness route need the local 2.0.1
  plugin code before a new gateway readiness check can pass. Missing routes are
  reported as a failure with installation/REST recovery guidance.
- A bit-for-bit clone with the same URL, salts, credentials and externally visible
  identity cannot be distinguished cryptographically from the original by the
  available baseline contracts. Changed-host clones, stale evidence and old grant
  generations are rejected; no stronger clone-detection claim is made.

## Focused verification

- `node --test gateway/tests/connection.test.mjs`: clean connection, HTTPS failure,
  REST blocked, OAuth unavailable, Basic gate, authorized-but-failed verification,
  successful authenticated read/recovery, limited account, stale/cache/clone checks,
  generation/user/site isolation, Authorization forwarding and form protection.
- Existing gateway connection/OAuth/refresh/site-management regression cases run
  with the new readiness fixtures and local migration.
- `php tests/connection.php`: local warnings, Application Password advisory,
  administrator/multisite checks, nonce/header echo, no-store, REST loopback and
  stale/cloned/user/connection-bound read observations.

All network and WordPress/database operations in these tests are mocked or use
disposable in-memory SQLite. No live site was read or modified, no deployment or
release was published, and approvals, updater, image search and skills were not
changed by this pass.

## Final routing acceptance

The prior root-only/pretty-only blockers are resolved. The pinned connection
checker explicitly accepts subdirectory installations, and pinned REST code
recognizes front-controller `rest_route` requests. Both focused regressions,
root/subdirectory OAuth routing cases and the full final acceptance suite now
pass. Six real-WordPress bridge cases cover pretty/plain REST and differing
WordPress-directory/root-home layouts, including signed clients, PKCE, verification,
exact approvals, refresh and revocation. No plugin PHP behavior changed in this
repair; only the four gateway routing/binding modules and related tests/docs did.
See `FINAL-ACCEPTANCE-2.0.1.md` for the PASS result and remaining hosted evidence
limits. No deployment or publication occurred.
