# Unlimited WP MCP Next

Independent GPL-2.0-or-later development fork of Awesome Motive / SeedProd's
WordPress plugin. See [UPSTREAM.md](UPSTREAM.md) for provenance and attribution.
This is not a WPVibe or SeedProd product and has no affiliation or endorsement.

Development version: **2.0.0-dev**. Do not install on production.

WordPress handlers run behind Unlimited's own local OAuth/application-password
authentication, exact-argument browser approvals, audit events and connection-scoped
operation receipts. Handler REST routes are private in-process descriptors. The
public MCP endpoint is `/wp-json/unlimited-wp-mcp-next/v1/mcp`. The intended
multi-site control plane in `gateway/` uses a distinct grant per site. Local gateway
tests pass; end-to-end integration remains pending.

The vendor relay, hosted preflight, recipe feed, signed-worker approval keys,
onboarding and updater are removed from active source. Historical upstream
documentation is preserved under `upstream-history/` and is not setup guidance.
No vendor account, hosted quota, signature impersonation or service bypass is used.

The source currently exposes **51 tools** and retains **131 native CLI entries**.
SQL, search-replace and optional background execution use local exact-operation
approvals. Image discovery uses Openverse with license/creator metadata; the
provider's limits apply, and errors are never bypassed or automatically retried.
Workflow guidance is authored locally. See [MIGRATION.md](MIGRATION.md).

Full runtime parity is **pending**, especially browser-based builder compilation,
builder-specific acceptance and MySQL-specific behavior. No production acceptance
is claimed. WordPress 6.6+, PHP 8.1+ and OpenSSL are required.

All mutations need one fresh, exact-operation browser approval. If execution has
started and its result is uncertain, inspect its receipt; never blindly retry.
