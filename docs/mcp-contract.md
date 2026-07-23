# Timeline MCP contract

All tools authenticate with a first-party opaque Timeline bearer token issued through Authorization Code with S256 PKCE. The token resolves to a user and the server derives that user's tenant; no request contains `tenant_id`.

## `get_curation_context`

Requires `read:curation-context`. Returns active topics, unexpired directives, recency-weighted explicit feedback signals, deterministic limits, research instructions, and a SHA-256 `context_version`.

## `begin_curation_run`

Requires `write:curation-runs`.

Input: `context_version`, 1–20 `exact_queries`, and optional `skill_version`. The context must still be current. A tenant may start at most three runs per UTC application day.

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
