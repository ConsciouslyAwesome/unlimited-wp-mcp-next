# Unlimited WP MCP Next

Independent GPL-2.0-or-later development fork of Awesome Motive / SeedProd's
WordPress plugin. See [UPSTREAM.md](UPSTREAM.md) for provenance and attribution.
This is not a WPVibe or SeedProd product and has no affiliation or endorsement.

Plugin version: **2.0.1**. Release publishing and live installation are separate
from source validation.

WordPress handlers run behind Unlimited's own local OAuth/application-password
authentication, exact-argument browser approvals, audit events and connection-scoped
operation receipts. Handler REST routes are private in-process descriptors. The
public MCP endpoint is `/wp-json/unlimited-wp-mcp-next/v1/mcp`. The intended
multi-site control plane in `gateway/` uses a distinct grant per site. Local gateway
tests pass; isolated staging integration has been exercised through the private
Next gateway. Production remains frozen.

The vendor relay, hosted preflight, recipe feed, signed-worker approval keys,
onboarding are removed from active source. Unlimited-owned release discovery and
the deferred updater preserve WPVibe 1.20.5 update behavior; see [RELEASING.md](RELEASING.md).
Historical upstream
documentation is preserved under `upstream-history/` and is not setup guidance.
No vendor account, hosted quota, signature impersonation or service bypass is used.

The gateway exposes **28 public compatibility tools**; the WordPress backend
retains **51 native descriptors** (48 where WordPress lacks the optional Abilities
API) and **131 native CLI entries**.
SQL, search-replace and optional background execution use local exact-operation
approvals. Public image discovery accepts query/count/orientation, uses optional Unlimited
Unsplash credentials and Openverse fallback, and preserves attribution/licensing.
Independent providers retain their own limits. Hidden native resource calls keep
their original behavior; see [IMAGE-SKILL-PARITY.md](IMAGE-SKILL-PARITY.md).
Workflow guidance is authored locally. See [MIGRATION.md](MIGRATION.md).

The fork preserves upstream builder handlers; it does not independently certify
commercial builders or claim broader behavior than upstream. The original
fork-and-decouple objective is documented in [MIGRATION.md](MIGRATION.md).
Package verification is separate from deployment acceptance. WordPress 6.6+,
PHP 8.1+ and OpenSSL are required.

Exact-operation browser approval is the default. An administrator may explicitly
enable the baseline's dangerous bypass; a browser approval may remember eligible
SQL for 30 minutes or 25 statements. See [APPROVAL-PARITY.md](APPROVAL-PARITY.md).
If execution has started and its result is uncertain, inspect its receipt; never
blindly retry.
