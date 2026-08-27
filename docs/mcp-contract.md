# Timeline MCP contract

All tools authenticate with a first-party opaque Timeline bearer token issued through Authorization Code with S256 PKCE. The token resolves to a user and the server derives that user's tenant; no request contains `tenant_id`.

## `get_curation_context`

Requires `read:curation-context`. Clients provide optional `plugin_version`; version 0.5 clients with `read:job-search-context` also receive non-sensitive job-search context. The response returns active topics, unexpired directives, recency-weighted explicit feedback signals, deterministic limits, research instructions, plugin compatibility status, an update command, and a SHA-256 `context_version`. Users can set their tenant-owned daily run limit from 1–10 on the Policy page; new and existing tenants default to 10 runs per day.

## `begin_curation_run`

Requires `write:curation-runs`.

Input: `context_version`, 0–20 story `exact_queries`, 0–20 `job_queries`, and optional `skill_version`. At least one query group is required and the context must still be current. A tenant may start only the configured number of runs per application day.

## `submit_story_batch`

Requires `write:story-batches`.

Input: `run_id`, `context_version`, and 1–10 story clusters. Each cluster contains:

- Stable `client_item_id` and factual `title`.
- `summary_points` with one to six concise, topic-appropriate strings. `technical_bullets` remains a temporary compatibility alias.
- Optional `why_it_matters`.
- One to five inspected HTTPS `sources`, with exactly one `primary`.
- Optional `media` with at most three attributed image or video references. The curator must inspect the originating page and final asset, prefer publisher or primary-source visuals, verify that each public HTTPS asset loads without authentication or temporary tokens, and put the strongest hero visual first. Videos must be direct MP4/WebM files or recognized YouTube/Vimeo pages.
- Four to six story-specific `feedback_tags`, each containing a unique slug, display label, and stable preference signal. Legacy plugin submissions may omit these during the compatibility window.

The response separates `accepted` and `rejected` items. Rejections use stable codes including `policy_changed`, `duplicate`, `quota_exceeded`, `invalid_source`, `invalid_story`, `invalid_media`, `invalid_feedback_tag`, and `rule_violation`. Retrying the same `(run_id, client_item_id)` is idempotent.

## `complete_curation_run`

Requires `write:curation-runs`. Final status is `completed`, `completed_empty`, or `failed`.

Limits: 20 accepted clusters and 50 sources per run, five active topics, five sources per cluster, three media items per cluster, and 2,000 stored clusters per tenant.

## Job curation and application tools (version 0.5)

`get_curation_context` also returns an enabled job profile's non-sensitive search preferences, search/application readiness, job-feedback summary, job limits, and application queue counts. Contact, career, reusable-answer, and document data are withheld until the user approves one job.

### `submit_job_batch`

Requires `write:job-batches`. Input is an active `run_id`, immutable `context_version`, and one to ten job matches. Each match includes current listing/application destinations, employer and role facts, dates when known, concise requirements and fit analysis, one to five inspected HTTPS sources with exactly one primary, and four to six balanced job-specific feedback choices. Expired, duplicate, private-address, unverifiable, or malformed matches are rejected.

### Application queue

- `claim_next_application` requires `read:approved-applications`, a unique `client_attempt_id`, and atomically leases one approved application for 30 minutes.
- `get_profile_document` returns one encrypted private document that was present in that approved snapshot.
- `save_application_materials` requires `write:application-progress` and retains exact generated materials with approved profile fact paths.
- `request_application_information` pauses work and creates a safe server-rendered questionnaire. Arbitrary HTML/JavaScript, credentials, CAPTCHA, payment data, and full identity numbers are rejected.
- `record_application_outcome` records `submitted`, `attempted_unconfirmed`, `needs_manual_action`, or `failed`. `submitted` requires a web confirmation URL/time or email recipient/time/provider message ID.

OAuth 0.5 adds `read:job-search-context`, `write:job-batches`, `read:approved-applications`, and `write:application-progress`. Existing 0.4 story-only clients remain supported with their original scopes.
