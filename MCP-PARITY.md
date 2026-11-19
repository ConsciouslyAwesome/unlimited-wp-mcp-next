# MCP interface compatibility: local 2.0.1

Authoritative baseline: GPL WPVibe **1.20.5**, commit
`62bc77ccd1c15faf766f527a7935fe32f1b227d3`, especially its README MCP table and
`includes/class-wpvibe-rest.php`. Its hosted Worker/catalog is absent. The current
connected WPVibe catalog mentions later releases and was **not** used as a schema
baseline. This pass restores verifiable functional compatibility; it does not
claim an exact reconstruction of closed descriptions or MCP JSON schemas.

## 1. Exact verified matches

All **26 public names** in the pinned README are advertised. `connect_site`,
`list_sites`, and `remove_site` retain their existing Unlimited implementation.
There are **zero fully verified hosted MCP contracts**: the evidence does not
contain the hosted descriptions, complete input schemas, annotations or wrappers.
No complete contract is labelled an exact match.

The argument types, required markers, declared defaults, enums and patterns for
source-backed tools come from a reproducible, read-only extraction of the pinned
PHP route registrations. This is REST argument evidence, not a hosted MCP dump.
`gateway/src/baseline-routes.generated.js` contains that extraction; the generator
does not run any handler. Per-tool field matches appear in `MCP-PARITY.json`.

## 2. Adapters implemented

- `run_wp_cli` → `cli_run`.
- `read_file`, `write_file`, `edit_file`, `delete_file`, `list_files`,
  `search_files`, `get_file_outline` → corresponding internal `file_*` handlers.
- `discover_abilities`, `get_ability_info`, `run_ability` → `abilities`,
  `ability_info`, `ability_run`.
- `get_preview_url`, `publish_draft_theme` → `draft_theme_preview`,
  `draft_theme_publish`.
- `rest_api` → the appropriate existing REST read/write handler. Verified plugin
  routes such as `/wpvibe/v1/content/edit` translate to private Unlimited handlers,
  including builder routes; the vendor namespace is never used as a destination.
- `get_page_html` → existing server-rendered `rendered_html`.
- Existing names `site_info`, `create_draft_theme`, `delete_draft_theme`,
  `create_classic_theme`, `navigate`, `upload_media`, `search_images`, and
  `load_skill` retain their native implementations behind the URL envelope.
- `code_snippet` is retained based on the pinned PHP handler. Its advertisement
  as an MCP tool is not established by the README table. `operation_receipt`
  remains an explicitly identified Unlimited extension.

The resulting gateway advertises **28 tools**. Old Unlimited names remain callable
with their existing schemas as hidden aliases; they no longer replace compatible
names in discovery. Public calls use `site_url`, resolved only against the caller's
existing registry, instead of requiring an alias or internal site ID.

Public callers need not supply an internal `approval_id`. An encrypted pending
approval reference binds the existing native approval to the same user, site,
grant, native handler and canonical arguments. After approving in WordPress, repeat
the same public call. No call automatically retries. A conclusive pre-execution
`approval_invalid` response preserves the pending scope; uncertain outcomes leave
one-use execution protection intact. The existing native gate authorizes writes.

## 3. Closed-service behavior not reproduced

Exact descriptions, wrapper schemas and output formatting cannot be recovered
from the available GPL material. Descriptions are plainly reconstructed from
verified functionality. Hosted JavaScript rendering, selector extraction, browser
navigation UX, skill search/sub-resources/feed, Unsplash-specific search, file
read cache/formatting wrappers and hosted approval-panel/status UI are not claimed
as reproduced. Their underlying native functionality remains available where it
exists. No fleet, upload-session, quota-reset or other tools from the current live
service are added without pinned evidence.

## 4. Remaining schema and result differences

- `rest_api`: documented HTTP verbs, with native `path` and structured
  `parameters`. Hosted `route`, JSON-string `body`, query/field selection and
  truncation fields are unverified and were not inferred.
- Abilities: native `name` and `input`; hosted `ability_name`, hint flags and
  `input_json` are unverified and were not inferred.
- `get_page_html`: native optional `path`, server-rendered `{html,url}` result.
  Hosted `url`, `selector`, `max_length` and browser execution are not inferred.
- `search_images`: existing independent provider's `query`, `page`, `limit`.
  `load_skill`: existing required `name` enum for four local workflows. Their
  optional `site_url` is needed when multiple connected sites make routing
  ambiguous; no hidden default site is selected.
- All tools: hosted description text, MCP required/default policy, numeric
  distinctions, annotations, `additionalProperties`, target-field requirement
  and response wrappers are unavailable. Source-backed field evidence is tracked
  individually in the JSON report. Native safety limits and validators still
  apply even when the raw PHP registration has fewer bounds.
- Receipts, security errors, approval URLs and safe diagnostic metadata remain
  Unlimited-shaped. No native response is reformatted to impersonate an unknown
  hosted response.

## Focused local verification

`node --test gateway/tests/compatibility.test.mjs` checks discovery, source-backed
fields, defaults/enums, translations, URL isolation, aliases, output preservation,
encrypted approval handoff and parameter injection. The existing gateway catalog
checks are updated to the compatible public surface. Read-only baseline extraction
and report checks verify provenance and detect drift.

The new `0001_interface_compat.sql` migration adds encrypted pending references to
the **existing** D1 database binding. It has only run in disposable in-memory test
databases. No deployment, hosted migration, live WordPress test, new Site/database,
plugin publication, release or unrelated WordPress behavior change was performed.


## Image/skill follow-up

The image and skill interface details above are superseded by
`IMAGE-SKILL-PARITY.md`: public image calls now use `query`, `count`, `orientation`;
skill calls use optional `name`, `query`, `resource` or no arguments for the local
catalog. Existing optional `site_url` targeting is preserved. Native aliases
with `site` still use their original handlers. The pinned hosted schemas and
proprietary skill library remain unavailable; no exact hosted-match claim is made.
