# Migration status — 2026-10-22

Upstream identity is pinned in UPSTREAM.md. This development branch adapts GPL
WordPress functionality to an independent Unlimited control plane. It is not a
production release or a functional-parity acceptance claim.

| Source capability | Disposition | Current status |
| --- | --- | --- |
| Content, files, draft themes, classic themes, previews, custom fields | Adapt GPL source | Private descriptors behind local auth/approvals; broad runtime parity pending |
| Elementor, Beaver, Breakdance, Bricks, SeedProd compile-login minting | Adapt GPL source | Imported and gated; actual builder integrations and compile automation pending |
| Native CLI | Adapt GPL source | All 131 upstream allowlist entries preserved; raw SQL and broad search-replace intentionally still refused by inherited Unlimited policy pending security integration |
| SVG sanitizer, Alpine | Reuse | Original asset/license text preserved |
| Tailwind preview | Adapt | Locally bundled 4.1.13 with MIT notice; no browser CDN dependency |
| Hosted worker signatures and approval bypass | Replace | No vendor key accepted; local exact-operation, expiring, single-use browser approval |
| Hosted OAuth, routing, catalog | Replace | Own local auth and separate 49-tool multi-site gateway source; 50 tools including list_sites |
| Audit and operation receipts | Replace | Local user/connection-scoped receipt events; gateway scope also binds user/site/grant/tool/arguments |
| Hosted preflight, recipes, account links, promotional UI | Replace/remove service dependency | Removed from active code; upstream documentation kept only as historical attribution |
| Hosted image search, progressive skill content | Rebuild independently | Not present in GPL repository; pending independent provider/content integration |
| Detached jobs and hosted browser orchestration | Rebuild independently | Pending; no inherited public job execution route |
| Upstream self-update | Replace | Refuses upstream update path; independent verified-release workflow pending |

Static audit records every literal hosted-domain match, every route-registration
location, HTTP call and worker-proof review location from the pinned upstream.
The source retains all 131 CLI allowlist entries; this does not mean every entry
has runtime acceptance. The current catalog contains 49 tools, 27 reads and 22
approval-gated operations. A second draft-delete route is replaced by the same
delete_draft_theme operation with the local snapshot guard. Vendor control-plane
routes are replaced rather than exposed as tools.

Verified locally: 65 PHP syntax checks; 37 isolated WordPress integration checks;
49 multi-site gateway checks using in-memory SQLite and mocked HTTP. Smoke
operations made zero outbound HTTP requests. No active-source vendor domain or
public approval-key matches remain. Root GPL and sanitizer LICENSE hashes match
the initial clone. The six imported Unlimited local control-plane files were
compared byte-for-byte to the accepted 1.0.0 ZIP before adaptation, whose SHA-256
is 2cfdfab36949046a620f5b36dbe7d2f6f2df7a82bc38bd5f733e84cd256cba9a.

Production WordPress, existing gateways, Site registrations and accepted release
artifacts were not changed. No production deployment is authorized. Continue with
the remaining security/capability gaps, builder fixtures, and isolated end-to-end
gateway-to-WordPress tests before packaging a candidate.
