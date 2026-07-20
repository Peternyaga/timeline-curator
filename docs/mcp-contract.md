# Timeline MCP contract

All tools authenticate with an Auth0 bearer token. Tenant identity is derived from `sub`; no request contains `tenant_id`.

## `get_curation_context`

Requires `read:curation-context`. Returns active topics, unexpired directives, recency-weighted explicit feedback, deterministic limits, research instructions, and a SHA-256 `context_version`.

## `begin_curation_run`

Requires `write:curation-runs`.

Input: `context_version`, 1–20 `exact_queries`, and optional `skill_version`. The context must still be current. A tenant may start at most three runs per UTC application day.

## `submit_story_batch`

Requires `write:story-batches`.

Input: `run_id`, `context_version`, and 1–10 story clusters. Each cluster has a stable `client_item_id`, title, exactly three technical bullets, optional significance, and one to five HTTPS sources. Exactly one source is primary, and the evidence mapping must collectively cover bullets 1–3.

The response separates `accepted` and `rejected` items. Rejections use stable codes including `policy_changed`, `duplicate`, `quota_exceeded`, `invalid_source`, `invalid_story`, and `rule_violation`. Retrying the same `(run_id, client_item_id)` is idempotent.

## `complete_curation_run`

Requires `write:curation-runs`. Final status is `completed`, `completed_empty`, or `failed`.

Limits: 20 accepted clusters and 50 sources per run, five active topics, five sources per cluster, and 2,000 stored clusters per tenant.
