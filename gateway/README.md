# Independent next-generation multi-site gateway

Development source only. No production Site identity, deployment metadata,
credentials or registrations are copied. The demonstration hosts in DEFAULT_SITES
are example.com fixtures; replace registry seeds only in a new isolated environment.

Adapted from the existing Unlimited multi-site gateway. Authentication, encrypted
per-user/site/grant credentials, OAuth/PKCE, exact-argument approval scoping and
audit diagnostics remain independent of WPVibe. Endpoints exclusively use the
next-generation WordPress namespace. Tests mock all WordPress HTTP requests.

Run `npm test` with Node 22.13+ (node:sqlite). The private hosting platform must
supply authenticated user identity, a separate D1 DB and a fresh encryption key.
Never point this development gateway at an accepted production database.
No deployment is authorized by this source preparation.
