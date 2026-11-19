# Unlimited plugin updates

Final local 2.0.1 acceptance is PASS after the two installation-routing repairs.
See `FINAL-ACCEPTANCE-2.0.1.md` for evidence and the future release/deployment
sequence. This result does not authorize packaging, publication or deployment.

Unlimited WP MCP Next 2.0.1 discovers updates only from published stable releases
of `ConsciouslyAwesome/unlimited-wp-mcp-next`. It does not use WPVibe's update feed
or GitHub's generated repository source archives.

Release tags must be `2.0.1` or `v2.0.1` (three numeric version components).
Attach an asset named **unlimited-wp-mcp-next.zip**. Its root directory must be
`unlimited-wp-mcp-next/`, containing `unlimited-wp-mcp-next.php`, `includes/`,
`assets/`, and the other plugin runtime files and license notices. Exclude the
gateway, tests, Git metadata, development credentials, and local runtime files.
The plugin header, `UWNext_VERSION`, readme stable tag, and release tag must agree.
The updater requires no GitHub credentials; the release asset must be public.

WordPress's normal update check uses the plugin's `Update URI` and a filter scoped
to this plugin's basename. Metadata is cached per network for one hour; failures
are cached for five minutes. Drafts, prereleases, malformed responses, and releases
without the exact installable asset do not generate update offers. No other
plugin's update source or automatic-update enrollment changes.

The restored WPVibe 1.20.5 self-update flow uses Unlimited names and the discovered
WordPress update offer. `plugin update unlimited-wp-mcp-next` still passes through
the existing local approval flow. `--expect-version` is checked before scheduling
and again at execution. Execution detaches the response on PHP-FPM/LiteSpeed, or
uses the existing one-time-token loopback fallback. It retains updater locking,
rollback/fatal-check handling, active/network-active restoration, and status in
`uwnext_self_update_state`. Automatic updates remain opt-in through WordPress's
network-aware `auto_update_plugins` site option. No new cron jobs are scheduled
for self-updates; the inherited legacy hook is retained for parity.

Existing 2.0.0 installs have discovery disabled (`Update URI: false`) and require
one manual installation of 2.0.1 through WordPress before they can discover future
Unlimited releases. Implementing this mechanism does not publish a GitHub release
or install anything on a live site.
