# Independent next-generation multi-site gateway

Local 2.0.1 interface pass: discovery advertises the pinned README's 26 names,
the native code-snippet handler and Unlimited operation receipts (28 tools).
The 51 native WordPress descriptors remain internal; old calls with `site` still
work as hidden aliases. New calls use `site_url`; after local browser approval,
repeat the identical public call. See `../MCP-PARITY.md` for evidence boundaries,
closed-service gaps and the new local-only `0001_interface_compat.sql` migration.

Local 2.0.1 source only. No production credentials or registrations are copied.
There are no production registry seeds. Example entries exist only in test
fixtures. Migration 0003 removes untouched, unauthorized legacy examples; records
with authorization state or changed identity require operator review.

Adapted from the existing Unlimited multi-site gateway. Authentication, encrypted
per-user/site/grant credentials, OAuth/PKCE, exact-argument approval scoping and
audit diagnostics remain independent of WPVibe. Endpoints exclusively use the
next-generation WordPress namespace. Tests mock all WordPress HTTP requests.

Run `npm test` with Node 22.13+ (node:sqlite). The private hosting platform must
supply authenticated user identity. A separately authorized production redeploy
must retain its existing Site identity, D1 binding/data and OAuth encryption key;
apply migrations 0001–0003 to that same database after backup. Fresh isolated test
environments use a distinct database and encryption key.
No deployment is authorized by this source preparation.
