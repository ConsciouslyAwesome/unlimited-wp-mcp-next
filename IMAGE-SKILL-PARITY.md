# Image and skill interface parity — local 2.0.1

## Evidence and limits

Authority: pinned GPL WPVibe 1.20.5 commit
`62bc77ccd1c15faf766f527a7935fe32f1b227d3`, particularly its README MCP tool
table and changelog. These establish `search_images` (Unsplash) and `load_skill`
(progressive WordPress workflow instructions). They do **not** ship the hosted
tool descriptions, JSON schemas, output wrappers, skill library or lookup logic.
No saved historical `tools/list` or hosted source is available.

The user's requested `query/count/orientation` and `name/query/resource` shapes
are implemented. Current connected WPVibe tool declarations corroborate those
field names, image count 1–10 and orientation values, but are not dated evidence
of the 1.20.5 contract. Exact historic field requirements, defaults, enum policy,
descriptions, annotations, unknown-field handling and responses remain unverified.
No byte-for-byte hosted parity is claimed. No later WPVibe content was imported.

## Restored public behavior and Unlimited adaptations

- `search_images`: required nonempty `query`, optional integer `count` (1–10,
  default 10), optional `orientation` (`landscape`, `portrait`, `squarish`).
  Default 10 carries forward Unlimited's previous search size; its historic
  WPVibe value cannot be established. Query length 200 and strict unknown-field
  rejection are Unlimited bounds, not claims about the closed gateway.
- No public `page`, `limit` or provider-specific parameters. At most `count`
  results are returned; fewer or zero is valid. No invented padding results.
- Optional gateway secret `UNSPLASH_ACCESS_KEY` enables an independently
  authorized Unlimited Unsplash application. It is sent only in the fixed
  Unsplash endpoint's Authorization header. No credentials have been provisioned
  or copied. Without that secret, use public Openverse. An Unsplash request,
  status, parse, size or network failure falls back once to Openverse. Empty
  successful results do not trigger a second search. Both failures produce a
  generic recovery message without provider bodies or credentials.
- Provider-specific orientation maps internally to Unsplash orientation or
  Openverse `aspect_ratio` (`wide`, `tall`, `square`). Providers define their
  actual aspect-ratio boundaries; identical rankings/sets cannot be promised.
- Normalized results include ID, title, HTTPS image URL, thumbnail/preview,
  source URL, creator/profile, attribution, license/version/license URL,
  provider and dimensions where supplied. Missing metadata is null, never an
  invented license. Openverse credit/license strings are preserved (bounded to
  8,000 characters); original HTTP source/license links remain metadata only.
  Non-HTTPS image URLs are excluded. All provider metadata is marked untrusted.
- Unsplash results retain hotlink image URLs and photographer/source links,
  explicitly identify the Unsplash License, and include download tracking URL
  and usage guidance. This search does not select/download photos, trigger
  tracking, or modify `upload_media`. Any consuming application must follow
  Unsplash attribution/hotlink/download requirements before using that provider.
- Requests use fixed independently operated provider endpoints, manual redirect
  refusal, 10-second timeout per provider and a 1 MiB response limit. No gateway
  usage quota/account logic is added. Independent providers can still rate-limit.
- `load_skill`: no arguments returns the four local skills; exact `name` returns
  guidance; `query` performs deterministic keyword lookup. A unique highest
  positive match loads the skill; ties return ranked candidates; no matches
  return the catalog. Exact name takes precedence over query. These are narrow
  independent lookup rules, not reconstructed proprietary ranking.
- `resource` requires `name`. Each local skill exposes `workflow`, returning its
  full independently authored guidance. Unknown names/resources fail clearly.
  This supports resource loading without inventing a proprietary hierarchy.
- Existing optional `site_url` is preserved. Calls still require an authenticated
  user and a valid registered connection. Without an explicit target there must
  be exactly one connected site. Resource calls cannot bypass connection checks
  or change authorizations. Old internal calls using `site` retain the native
  WordPress resource handlers and their original arguments.

Provider implementation references (current API contracts, not historical WPVibe
evidence): [Unsplash documentation](https://unsplash.com/documentation),
[Unsplash attribution requirements](https://help.unsplash.com/en/articles/2511315-guideline-attribution),
[Openverse API](https://api.openverse.org/v1/).

## Independently authored replacements

Existing Unlimited `workflows/content.md`, `theme.md`, `builders.md`, `cli.md`
are retained unchanged and bundled in `gateway/src/skills.generated.js` for the
gateway runtime. Their source text is tested against those local files.
Catalog metadata, keyword ranking, resource dispatch, provider normalization and
adapter code are independently authored. No WPVibe hosted skill text, API keys,
signed service credentials, vendor endpoints or proprietary imports were added.
Skills grant no authorization and do not change any approval policy.

## Remaining user-visible differences / unverifiable hosted behavior

The complete 1.20.5 hosted skill names, their actual instructions, resource names,
catalog format, no-argument support, query matching/confidence behavior and
resource hierarchy cannot be proved from the pinned GPL material. Only the four
existing local names are offered; none are asserted to be historic WPVibe names.
The no-argument catalog and `workflow` resource are explicit Unlimited adapters.
No proprietary skill content was recreated from guesses.

Exact hosted image output keys, count default/coercion/clamping behavior,
orientation default, pagination policy, result ranking, error format and license
presentation are unavailable. This adapter uses strict integer validation and
explicit metadata, rather than claiming those policies were present in 1.20.5.
Openverse results and licensing differ from Unsplash. No historic hosted wrapper,
quota/account service or undocumented caching behavior is impersonated.

## Focused verification and scope

`node --test gateway/tests/image-skill.test.mjs gateway/tests/compatibility.test.mjs`
covers public fields, count defaults/bounds, orientation mapping, attribution and
licensing, credentials/header isolation, provider fallback/failure/empty responses,
catalog/name/query/resource lookup, local content provenance, and registered-site
isolation alongside existing exact-operation adapter checks. Network calls are
mocked; there are no live provider or WordPress write tests. Provenance checks
verify local sources and absence of vendor endpoints/embedded keys in the new
implementation; they do not claim to compare against an unavailable hosted corpus.

Result: 34 focused tests passed; the generated interface report drift check passed.

Only these resource adapters, their tests and parity documentation changed in
this pass. Updater, approvals, connection implementation, unrelated handlers,
OAuth, encryption and receipts were not changed. Nothing deployed or published.
