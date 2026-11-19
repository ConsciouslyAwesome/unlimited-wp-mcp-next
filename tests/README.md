# Local validation

Focused connection checks: `node --test gateway/tests/connection.test.mjs` and
`php tests/connection.php`. These mock all network and WordPress operations; gateway
status storage uses in-memory SQLite only.

Focused interface checks: `node --test gateway/tests/compatibility.test.mjs`.
`python tests/generate-compat-baseline.py --check` checks the pinned GPL route
snapshot (set `PHP_BINARY` when PHP is not on PATH), and
`node tests/generate-interface-report.mjs --check` verifies the parity report.

Focused approval checks: `php tests/approvals.php` and
`php tests/approvals.php sql`. These use only in-memory doubles and exercise the
local approval gate and native SQL restrictions; no live WordPress writes run.

Updater-only checks require PHP 8.1+ and no WordPress runtime: run
`php tests/updates.php`. These use in-memory WordPress API doubles, mocked release
responses, and disabled file modifications; no HTTP or live WordPress writes run.

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
compile behavior would require authenticated licensed environments, but separate
commercial-builder certification is outside the fork-and-decouple objective.
No local test result is a production, MySQL or live-provider acceptance claim.

Final local acceptance also runs `python tests/acceptance-audit.py` and
`node --test gateway/tests/*.test.mjs`. `tests/acceptance.php` is included by the
WordPress integration runner and checks real cookie/nonces, bypass receipts and
core update visibility using mocked HTTP. `PHP_BINARY` selects the local PHP
executable for the integration and pinned-schema runners. No hosted database is
used. For a complete 51-descriptor catalog check, use WordPress with Abilities
support; WordPress 6.8 has only 48 descriptors.

Strict final baseline acceptance additionally runs
`node --test tests/acceptance-routing.test.mjs`. Its two repaired routing regressions
cover subdirectory and plain-permalink installations. Also run
`node --test tests/acceptance-wordpress-routing.mjs` after `tests/run-local.py`
to exercise six isolated real-WordPress routing/OAuth cases with the workspace PHP
runtime selected through `PHP_BINARY`. No network listener or outbound HTTP is used. See FINAL-ACCEPTANCE-2.0.1.md.
Updater recovery doubles run with `php tests/update-recovery.php`.
