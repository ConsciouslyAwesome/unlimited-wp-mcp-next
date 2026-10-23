# Local validation

The harness runs on the repository workspace's local WordPress core, SQLite
drop-in, default theme and PHP runtime under `.runtime/`. It creates and resets
only `.runtime/next-generation-fixture`, after checking its resolved location.
Outbound HTTP is denied; the optional provider response is mocked explicitly.

Run `python tests/run-local.py` from this repository, then
`python tests/generate-catalog.py --check` and `node --test gateway/tests/*.test.mjs`.
The gateway checks require Node with `node:sqlite` support and mock WordPress HTTP.

`UWNEXT_SOURCE` can select an independently extracted plugin package while the
test harness remains outside the package. `UWNEXT_WORKSPACE` selects the workspace
holding the local runtimes. `UWNEXT_EVIDENCE` is `source` or `package` and separates
their evidence. The plugin package contains no tests or gateway credentials.

These fixtures do not contain the commercial builders. Their real browser and
compile behavior requires an authenticated disposable builder environment.
No local test result is a production, MySQL or live-provider acceptance claim.
