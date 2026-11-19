# Final 2.0.1 acceptance audit

Date: 2026-11-18. **Result: PASS — routing and explicit-reconnect repairs verified locally.**

## Explicit OAuth reconnect follow-up

Production diagnosis confirmed that consciouslyawesome.com's stored client ID is
rejected by WordPress with HTTP 400 `invalid_client`. The site identity, redirect,
issuer, scopes, endpoint contracts and PKCE request shape matched the current flow.
This follow-up does not declare the live connection repaired or authorize release.

The smallest runtime change is in `gateway/src/index.js`: `authorizationURL()`
accepts an internal reconnect flag, supplied only by the existing same-origin
browser `/oauth/connect` handler `connect()`. Explicit reconnect always registers
a fresh client for that same user/site, replaces only its cached registration,
and creates a fresh encrypted PKCE verifier/state. Normal MCP `connect_site` on
an already connected site remains idempotent and does not register again. Existing
grants, approval bindings, registry rows, aliases, canonical URLs and encryption
context are not changed while preparing reconnect. No public schema was added.

`includes/local-auth.php`, `UWNext_Auth::register_client()` and `client()` prove:
signed client IDs contain no explicit expiry; their HMAC depends on WordPress
`wp_salt('secure_auth')`; site binding must equal the current canonical WordPress
installation URL; an included issuer must equal the current issuer. Registration
is self-contained, not a persistent WordPress client row. The actual historical
invalidation trigger for this live client remains unconfirmed: `invalid_client`
does not by itself prove a salt rotation or identify which validation failed.

Regression coverage adds cached-client replacement with a matching issuer (so it
cannot pass merely through the old issuer-mismatch fallback), repeated fresh
PKCE/state, unchanged grants/registry/aliases and unrelated-site registration.
The seven existing root/subdirectory/pretty/plain routing lifecycle tests now
also exercise explicit reconnect. First registration and normal connected-site
idempotence remain covered by existing tests. Compared with the accepted routing
runtime manifest, only `gateway/src/index.js` changed; no PHP, updater, approval,
image/skill, catalog, migration, environment or production source was altered.

Live authorization remains pending. The current deployed gateway still reuses
the invalid cached client, and this local repair has not been deployed. A working
manual authorization session requires either this approved repair to be deployed
in a separately authorized step, or a supported targeted replacement of that
site's cached client followed by a fresh gateway PKCE flow. No such production
replacement or deployment was performed in this local acceptance pass. Do not
reuse the prior rejected/expired links or resume the release automatically.

This audits the current local working tree, including the uncommitted 2.0.1
repairs, against GPL WPVibe 1.20.5 commit
`62bc77ccd1c15faf766f527a7935fe32f1b227d3`. The two verified installation-routing
failures from the prior audit are now repaired and their acceptance regressions
pass. The full local acceptance suite has been rerun. Remaining release blockers:
**none**. This is source acceptance; it does not authorize packaging, publication
or production changes.

Nothing was packaged, published, deployed or installed on a live site. No live
WordPress reads/writes, production database queries, or production configuration
changes were performed. Official WordPress test dependencies were downloaded only
into the workspace's disposable `.runtime` directory. Earlier local release/deploy
artifacts were inspected only as historical comparisons and were not rebuilt.

## Resolved blockers and implementation scope

### B1 — subdirectory installations: PASS

The pinned connection checker explicitly accepts different WordPress and public
home addresses. Canonical registry URLs now preserve a clean installation path,
its case and its site identity. A trailing slash is normalized; the path is never
collapsed to the domain root. Administrator URLs use that WordPress installation
path. Same-host installations retain separate IDs, aliases, grants and ciphertext.
Approval URLs require the exact installation's admin path.

REST discovery supports the installation path or a same-origin ancestor home
address, including WordPress under `/wordpress` with its public homepage at root.
Fresh site/challenge evidence and exact generated endpoint contracts must agree;
a different host, sibling installation, cached response or arbitrary endpoint
cannot supply the routing destination. Traversal, encoded-path tricks, credentials,
queries/fragments in the registered address and private hosts remain rejected.

### B2 — plain-permalink REST: PASS

Readiness prefers working pretty REST. Before authorization it can detect the
standard `?rest_route=/...` and `index.php?rest_route=/...` front-controller forms,
including the form emitted by the actual WordPress 6.9 runtime. WordPress's
query-free plain-permalink issuer is preserved. Token registration, exchange,
verification, normal MCP routing, refresh and revocation consistently use the
selected profile. Existing pretty-root refresh behavior is retained.

The profile contains only a validated REST base and standard REST form. It is
encrypted with the pending PKCE flow, then with credentials using the unchanged
user/site/canonical-URL/grant encryption context. Callback issuer/resource checks
stay exact. Refresh preserves both the grant generation and routing profile.
An existing stored connection is never silently rerouted from a new preflight;
changing the configured REST form requires fresh authorization. Fallback performs
only readiness GETs before authorization, never retries token exchange or an
uncertain operation against another endpoint. Revoke accepts the plugin's valid
JSON-null success response, confirmed by the real WordPress routing tests.

No database schema migration, new encryption key or new environment setting is
needed for these two repairs. Existing `{a,r}` encrypted root-site credentials
and legacy string PKCE flows retain their original root/pretty behavior. New
routing data stays inside the existing encrypted columns. Cached signed OAuth
clients are reused only when their issuer matches the detected profile; WordPress
still verifies their signatures and complete authorization request.

Runtime changes since the previous FAIL audit are limited to:

- `gateway/src/security.js`: canonical paths and validated endpoint construction.
- `gateway/src/connection-readiness.js`: pre-authorization REST-form detection.
- `gateway/src/index.js`: pin/use encrypted routing through the OAuth lifecycle.
- `gateway/src/store.js`: persist optional encrypted routing and bind approval URLs
  to the full canonical installation path.

A SHA-256 comparison to the prior audit's 123-file runtime manifest confirms all
other runtime files are unchanged. Plugin PHP handlers, updater, approvals, image
search, skills, catalogs, existing migrations and environment configuration were
not modified in this repair pass. Tests and the related acceptance documentation
are updated. No production seed code was added.

## Six repaired areas as an integrated system

### 1. Site management — PASS

`connect_site`, `list_sites`, `remove_site` are advertised and callable. Tests
exercise arbitrary registration, a one-click WordPress authorization link,
idempotent stored connections, aliases/stable IDs, per-user registries, 50 sites,
revocation attempts, disabled sites, duplicate/ambiguous targets and encrypted
separate grants. Browser forms retain same-origin protection and PKCE/state
consumption. Root, subdirectory, pretty and plain flows now pass; WordPress-directory/public-home-root combinations pass too.

The audit removed runtime example seeding. New users and repeated home/list calls
start with an empty registry. Fixtures now live solely in
`gateway/tests/registry-fixtures.mjs`. A mismatched stored credential URL no longer
causes `connect_site` to claim a connected site; it starts authorization again.

### 2. Update mechanism — PASS within local validation limits

The header Update URI, release API, package slug/asset and metadata consistently
use `ConsciouslyAwesome/unlimited-wp-mcp-next`. Stable, complete public release
assets are required; drafts, prereleases, malformed/rate-limited responses and
foreign packages fail closed. Core's real update transient assembly was tested
against mocked official/core and Unlimited feeds, confirming normal update
visibility. CLI discovery, dry-run and expected-version refusal are exercised.

The restored self-update and detach source retains the baseline out-of-band
request/loopback handoff, one-use token, updater locking, execution-time version
assertion, fatal-check rollback handling and active/network-active recovery.
Recovery doubles exercise successful update reporting, successful/failed rollback,
thrown/interrupted execution, fallback updater, failed activation warning,
shutdown recovery and ownership of maintenance state. Automatic updates remain
WordPress opt-in through the network-aware core option. No vendor update feed,
vendor credential or quota/account service participates.

No actual plugin replacement, FPM/LiteSpeed execution, live GitHub release
discovery or hosted upgrade occurred. An existing 2.0.0 installation still needs
its first manual 2.0.1 installation because its old Update URI disabled discovery.
No installable release asset was produced by this audit.

### 3. Approvals — PASS

Exact-operation approval remains the default. Bypass is OFF when its option is
absent. Enable/disable requires a logged-in administrator's matching browser
cookie and nonce; Application Password, OAuth and MCP execution contexts cannot
change it. Multisite enablement retains the super-administrator requirement.
Generic option writes cannot toggle the option. The warning/banner remains.

Bypass stays limited to the baseline CLI-approved/code-snippet route scope;
detached jobs retain exact approvals. Bypassed operations require an audit event
before execution and normal connection-bound receipts afterward. Always-ask
white-label enabling remains protected. Eligible remembered SQL is bounded to
30 minutes/25 statements including the first approved statement, without implicit
renewal; source-native hard refusals, transactional/code-storage protections and
schema/other-operation exceptions remain. Changed arguments, users, connections,
expired approvals and replay are rejected.

Real WordPress cookie/nonce enable/disable, MCP refusal to write the bypass option,
native CLI bypass execution, connection-bound logging and restoration of the
normal exact gate were tested together. The invalid mechanically renamed HTTP
header was corrected to `X-Unlimited-WP-MCP-Next-Bypass-Approvals` and tested in
the REST response filter chain.

### 4. MCP interface — PASS within the explicit evidence boundary

The public gateway advertises all **26 documented baseline names**, including
the three site-management names, plus native `code_snippet` and retained Unlimited
`operation_receipt`: **28 public tools**. The backend retains **51 descriptors**
with WordPress Abilities support; older core without that API supplies 48. The
prior 54-tool production catalog is not the current compatibility catalog.

Adapters preserve the native handlers and the verified GPL route field names,
defaults/enums and call semantics. Hidden native names with their old `site`
envelope remain callable for compatibility, and do not replace discovery's public
names. Exact browser approval handoff is encrypted and bound to the translated
operation; no uncertain write is replayed automatically. Verified route snapshot,
generated catalog and interface report checks pass. A stale CLI description in
the generated native catalog was synchronized with its tested handler source.

`MCP-PARITY.json` still reports **zero exact hosted tool contracts verified**.
Absent hosted descriptions/schemas are not asserted as known. The public resource
shapes explicitly requested by the user remain labelled adaptations, including
unverified historic count/default behavior. Accepted Unlimited safety bounds,
explicit targeting and receipts remain visible differences, not disguised matches.

### 5. Connection/preflight — PASS

Passing checks cover HTTPS, reachable REST/namespaces, OAuth availability,
Authorization forwarding, Application Password advisory, account capabilities,
Basic Auth/password gates, security/cache/CDN evidence, consent-page warnings,
fresh challenge/response correlation and authenticated post-OAuth `site_info`.
Connected, limited, failing, awaiting-confirmation and stored-unverified evidence
is scoped to user/site/URL/grant and expires. Changed-host clones, mismatched
request IDs, cached challenges and stale grant evidence cannot establish health.
Consent success followed by a failed read produces explicit recovery guidance.

The retained `connection_status` field means stored authorization; the separate
fresh `verification` object means observed health. A stored grant alone is not
a successful fresh read. B1/B2 are resolved by the path-preserving, pre-authorization routing above. Hosted implementation details remain subject to the explicit evidence limits below.

### 6. Image/skill interface — PASS within the explicit evidence boundary

`search_images` advertises `query`, `count`, `orientation` and the existing
optional site target. Openverse parameters stay internal. Image selection URLs,
preview/source/creator/attribution/license metadata are retained where supplied;
unknown metadata is not presented as a verified license. Optional
`UNSPLASH_ACCESS_KEY` is an Unlimited-owned configuration secret, sent only to
Unsplash; no key was provisioned. Provider failure/fallback and metadata are tested
with mocks. Independent providers retain technical/rate limits; Unlimited adds no
usage quota. Unsplash consumers must follow its returned attribution/hotlink and
selection/download-tracking guidance; this pass did not change media upload.

`load_skill` supports an empty-call catalog, exact name, deterministic query
lookup and the `workflow` resource on each of four existing local skills. Tests
match bundled text to the independently authored local workflow files. No hosted
skill import, proprietary content feed or vendor credential was added. Unknown
historical skill names/hierarchies are explicitly not fabricated.

## Drift audit

- **Fixed:** production example seeding; stale gateway package/server versions;
  invalid bypass HTTP header; mismatched-URL stored-connection shortcut; generated
  CLI description drift; obsolete PHP 7.4/8.0 lint matrix below the declared 8.1
  minimum; stale issue-template product name and current setup/version text.
- **Vendor dependencies:** active runtime scans and endpoint review found no
  WPVibe hosted domain, quota/account gate, known vendor approval key, private-key
  block, copied credential or vendor-only private endpoint. Fixed external
  endpoints are independently operated image providers, Unlimited's public GitHub
  release feed and retained WordPress.org package/checksum services. Site network
  destinations otherwise derive from the user's registry. This is a source audit,
  not a scan of production secret storage.
- **Later-version scope:** the authority remains the pinned GPL commit. All 131
  CLI allowlist entries are retained. New work implements requested Unlimited
  adapters, not later WPVibe skill text, tools or commercial features. Current
  connected tool metadata is never used to claim historical schema proof.
- **Restrictions/capability drift:** The former B1/B2 reductions of verified baseline installation support are fixed and tested. Existing accepted Unlimited
  exact-operation/file/SQL safeguards, extension-GET approval, explicit site
  targeting, bounded responses and fail-closed publication checks remain; these
  were not weakened in this audit. No additional removed native CLI entry or
  documented tool name was found. Closed browser/skill functionality is listed
  separately below rather than claimed to exist.
- **Fixtures/staging:** production source imports no test fixtures, has no default
  registry entries, staging hostname switch, staging credential or hardcoded Site
  identity. Test/example URLs remain in tests and the narrowly scoped cleanup
  migration only. `https://route.invalid` is a local URL parser base, never a
  network destination or registry row. Historical `../release-package/dist` still
  contains old seed code; it was not changed and must not be reused as 2.0.1.
- **Production versus release source:** the current source contains the registry,
  OAuth, encryption, approvals and receipts; it does not depend on a separately
  maintained production-only seed/config file. Live deployment contents/settings
  were not inspected, so byte-for-byte correspondence to production is unverified.
  Any later deployment must use the audited source and existing production
  identity/database/key, never the stale local deploy artifacts.
- **Versions/update source:** active plugin header/constant, readme stable tag,
  gateway package/server metadata and current documentation say 2.0.1. Historical
  2.0.0 records and synthetic future 2.0.2/2.0.3 test offers are intentionally
  retained. Protocol versions are separate from product versions. No release tag
  or published asset is asserted to exist.
- **Identity/GPL:** Unlimited is the current product identity. Original author/GPL,
  sanitizer and Alpine notices match the pinned source; bundled Tailwind retains
  its MIT notice. WPVibe references remain where needed for attribution,
  historical Bricks warnings, legacy template APIs/theme headers and protected
  legacy option names. Removing those would break provenance/compatibility.

## Production example-row cleanup — prepared, not applied

The gateway **no longer seeds** `https://original.example.com` or
`https://booking.example.com`. Removing source seeding does not delete existing
rows. Their actual presence in production has not been queried by this audit.

Prepared migration: `gateway/drizzle/0003_remove_example_seeds.sql`. Alongside the
two earlier local-only additive migrations, it must be reviewed against the
existing database's migration history before any later authorized application.
It removes aliases then registry rows only for these exact legacy combinations:

- ID `00000000-0000-4000-8000-000000000001`, original example URL, display name
  `Consciously Awesome`.
- ID `00000000-0000-4000-8000-000000000002`, booking example URL, display name
  `Booking`.

Rows with any OAuth client, flow, connection, or approval scope are preserved, as
are renamed rows and rows whose URL changed to a real installation. The migration
is idempotent and was tested transactionally with SQLite foreign keys enabled;
it preserves real grants. It performs no outbound revocation and does not touch
the encryption key, Site/plugin identity or real-site registry data.

After a separate authorization and database backup, the exact operator sequence is:

1. On the **existing** D1 binding, inspect migration history and list candidate
   identities and authorization presence with this read-only query:

   ```sql
   SELECT s.user_id,s.site_id,s.display_name,s.canonical_url,s.enabled,
     EXISTS(SELECT 1 FROM oauth_connections c WHERE c.user_id=s.user_id AND c.site_id=s.site_id) AS has_grant,
     EXISTS(SELECT 1 FROM oauth_clients c WHERE c.user_id=s.user_id AND c.site_id=s.site_id) AS has_client,
     EXISTS(SELECT 1 FROM oauth_flows f WHERE f.user_id=s.user_id AND f.site_id=s.site_id) AS has_flow,
     EXISTS(SELECT 1 FROM approval_scopes a WHERE a.user_id=s.user_id AND a.site_id=s.site_id) AS has_approval
   FROM sites s
   WHERE s.canonical_url IN ('https://original.example.com','https://booking.example.com');
   ```

2. Review and apply missing additive migrations `0001_interface_compat.sql` and
   `0002_connection_observations.sql` through the existing hosting migration
   mechanism before the gateway cutover. Apply the guarded
   `0003_remove_example_seeds.sql` only after the seed-free gateway is active,
   so old code cannot recreate its rows. Preserve the existing binding/data/key;
   do not create a replacement database. Do not run this during the local audit.
3. Repeat the query. Any preserved candidate needs per-user, per-ID review, not
   a domain-wide delete. If its URL is a legitimate corrected production URL,
   preserve it. If it has a grant, verify its intended site and revocation path
   before separately removing that authorization; never send stored credentials
   to an example domain merely to clean a row.
4. Confirm real registry entries, grants and decryption are unchanged. The seed-free gateway deployment must accompany cleanup so old home requests
   cannot recreate examples. No production cleanup was executed here.

## Validation results and limits

- Full WordPress integration on disposable **WordPress 6.9 / SQLite 2.2.9 /
  PHP 8.3.36**: **202 passed, 0 failed**, no fresh PHP diagnostics. This includes
  13 integrated acceptance checks for real cookie/nonce bypass and update assembly.
- Gateway suites (in-memory SQLite, mocked HTTP): **124 passed, 0 failed**.
- Updater discovery/handoff: **36 passed**; updater rollback/recovery doubles:
  **21 passed**; approvals: **64 passed**; native SQL restrictions: **22 passed**;
  PHP connection diagnostics: **20 passed**.
- **91 PHP files linted** without failures. Pinned 36-route schema snapshot,
  51-descriptor generated catalog, interface-report drift, GPL/source inventory
  and four integrated source-invariant groups passed.
- Strict baseline installation-routing acceptance: **2 passed, 0 failed**.
- New real-WordPress routing acceptance: **6 passed, 0 failed** across root,
  subdirectory, pretty/plain and WordPress-directory/root-home configurations.
  These use a stdio transport bridge and model server routing without outbound
  HTTP; native WordPress signed clients, PKCE/code/token processing, site reads,
  exact approvals, refresh and revocation run against separate disposable databases.
  Human consent/approval clicks are supplied by the fixture.
- Total: **497 passing runtime checks, 0 failures**, plus lint/static checks.
- An earlier WordPress 6.8.3 compatibility run passed 188 existing integration
  checks before adding the acceptance cases. It lacks three Abilities descriptors;
  the final full catalog was checked on 6.9. It is not a second full acceptance run.

Reproduce with the workspace PHP runtime selected through `PHP_BINARY`:

```text
python tests/run-local.py
node --test gateway/tests/*.test.mjs
node --test tests/acceptance-wordpress-routing.mjs
php tests/updates.php
php tests/update-recovery.php
php tests/approvals.php
php tests/approvals.php sql
php tests/connection.php
python tests/generate-catalog.py --check
python tests/generate-compat-baseline.py --check
node tests/generate-interface-report.mjs --check
python tests/audit-source.py
python tests/acceptance-audit.py
node --test tests/acceptance-routing.test.mjs
```

`tests/run-local.py` resets only its checked disposable fixture path. HTTP is
blocked or mocked. The new recovery suite replaces no real plugin files. Windows
symbolic-link cases unavailable to the existing fixture are explicitly reported
as unavailable; hard-link checks ran. MySQL, licensed builders, live providers,
actual package swaps and FPM/LiteSpeed behavior are not newly certified here.
Local evidence logs are under `../audit/next-generation/acceptance-*` and
`source-local-tests.txt`; they contain no production data.

## Documented hosted uncertainties — not blockers

- Exact historical hosted tool descriptions, schemas, annotations, numeric
  distinctions, defaults/required policy, wrappers and formatting absent from GPL.
- Proprietary skill names/content, lookup ranking and sub-resource hierarchy;
  historic no-argument catalog behavior. Local skills are independent replacements.
- Hosted Unsplash response/ranking/default/coercion details. Explicit requested
  public fields are supported, without claiming historical byte identity.
- Closed Worker approval/session UI, storage keys and undocumented SQL statement
  accounting edge cases. The documented 30-minute/25-statement limits are enforced.
- Hosted browser/JavaScript orchestration, selector handling and client UX;
  vendor relay/account aggregation, telemetry and signed-worker proof internals.
- A bit-for-bit clone with identical URL/salts/credentials is indistinguishable
  under the available contracts. Changed-host/stale/grant-mismatched evidence is
  rejected; no stronger proof is claimed.

These are evidence limits or intentionally replaced vendor services. The two
GPL-verifiable routing blockers have been restored and are no longer exceptions.

## Release decision and exact future release/deployment sequence

**PASS. Remaining release blockers: none.** The following is a sequence for a
later separately authorized release. None of these release/deployment steps ran
in this audit; no package, tag, release, deployment or production migration exists
as a result of this pass.

1. Review and record the complete accepted 2.0.1 working tree as a release commit.
   Include the prior repairs and these four routing changes; do not reuse the old
   `../release-package/dist` or old deployment checkout. Recheck the commands above
   against that exact source. Preserve the pinned 1.20.5 provenance and GPL notices.
2. Back up the existing production Site deployment/configuration and D1 database,
   and securely preserve the **existing** `OAUTH_ENCRYPTION_KEY`. Back up the target
   WordPress files/databases and retain their salts, plugin basename and stored
   connections. Do not create a new Site, database, gateway or ChatGPT plugin, and
   do not rotate the existing key as part of this release.
3. Build the plugin artifact from that release commit using an explicit runtime
   allowlist: `unlimited-wp-mcp-next.php`, `includes/`, `assets/`, `starter-themes/`,
   `third-party/`, `languages/`, `workflows/`, `LICENSE`, `UPSTREAM.md`, readme/release notices and
   the applicable bundled-license files. Root directory must be
   `unlimited-wp-mcp-next/`. Exclude `gateway/`, tests, Git data, local runtimes,
   credentials and test fixtures. Verify an extracted copy with the external
   test harness (`UWNEXT_SOURCE` pointing to it, `UWNEXT_EVIDENCE=package`), confirm
   header/constant/stable tag 2.0.1, and record its SHA-256. Do not include Site
   deployment files or database/config exports in the WordPress ZIP.
4. Create the stable GitHub release **`v2.0.1`** at that accepted commit in
   **`ConsciouslyAwesome/unlimited-wp-mcp-next`**, attaching the verified public
   asset **`unlimited-wp-mcp-next.zip`**. Confirm public feed metadata points to
   precisely that repository/tag/asset; do not use GitHub's generated source ZIP
   as the update package. Existing header Update URI and source configuration stay
   unchanged. Release publication needs its own authorization.
5. Install that verified 2.0.1 ZIP over the existing plugin on intended WordPress
   sites, preserving its directory/basename and data. Existing 2.0.0 installs need
   this first manual upload/replace because their old Update URI disabled update
   discovery. Keep administrator bypass OFF unless the owner has explicitly set
   it otherwise. Do not reset OAuth clients, credentials, salts or existing approvals.
   The plugin must precede the gateway update because the gateway requires its
   readiness endpoint. Do not upgrade any site during the current audit.
6. On the **existing production D1 binding**, inspect migration history and apply
   only missing additive `0001_interface_compat.sql` and
   `0002_connection_observations.sql`, transactionally after backup. Defer the
   guarded `0003_remove_example_seeds.sql` until the seed-free deployment is active.
   These routing repairs add **no** fourth migration. Keep real registry rows,
   grants and encrypted data unchanged.
7. Build the gateway from the same accepted source using the existing Site build
   workflow, including its compatibility/resource modules and generated catalogs.
   Redeploy only existing Site project
   **`appgprj_6aea1b1c5f988191836fb1674cea2637`**, retaining
   **`https://unlimited-wp-mcp-next.consciouslyawesome.chatgpt.site`**, its database
   binding, environment, authenticated-user boundary and encryption key. Preserve
   the existing private ChatGPT plugin identity and connection. Refresh its tool
   discovery if needed; do not create or publish a replacement plugin.
8. With the new seed-free deployment active, repeat the read-only example-row
   review above and apply the guarded `0003_remove_example_seeds.sql` through the
   existing database migration mechanism. Recheck for preserved candidates and
   review them individually; never delete a real grant or send credentials to an
   example host as part of cleanup. This step is deferred until separate production
   authorization and backup, just like the other deployment steps.
9. Perform only read-only acceptance on the deployed system: version 2.0.1,
   **28 public tools**, all documented compatibility names and the three site
   management tools, real registry retained without examples, and one fresh
   `site_info` per authorized verification target. Exercise new authorization only
   for intended root/subdirectory and pretty/plain verification sites, with their
   owner's consent. Check returned site identity and fresh verification state.
   Verify normal WordPress/CLI update discovery without installing a second update.
   Record the deployment ID, release commit and artifact checksum. No write tests.
10. If those checks fail, stop rollout. Preserve grants/data/key and use the saved
   deployment/plugin backup and existing recovery procedures. An older gateway
   cannot serve newly connected path/plain sites, so a gateway rollback may leave
   those sites temporarily unavailable; do not rewrite their identities or delete
   credentials to mask the failure. Re-audit before resuming.
