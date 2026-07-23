# Timeline Curator

Timeline Curator is a multi-tenant, feedback-trained research feed. The Laravel application stores each user's policy, evidence-backed story clusters, and explicit feedback. Independently authenticated Codex tasks perform web research and update the feed through a remote OAuth-protected MCP server.

The application never calls an LLM API to scrape, rank, summarize, or judge content.

## Architecture

- Laravel 13, PHP 8.3+, MySQL, Blade, Vite
- First-party email/password accounts and OAuth Authorization Code with S256 PKCE
- Official `mcp/sdk` Streamable HTTP server at `/mcp`
- One tenant per Timeline account, derived exclusively from the validated opaque token
- Tenant-scoped models plus composite tenant foreign keys for defense in depth
- Story clusters with exactly three technical bullets and mapped citations
- Explicit 1–5 relevance and depth feedback, controlled tags, and comments
- Repo-local private-beta Codex plugin in `plugins/timeline-curator`

## Local setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build
php artisan serve
```

Set a MySQL connection in `.env`. Timeline serves its own login, consent, registration, and token endpoints; no external identity provider is required. See [first-party authentication](docs/authentication.md) and the [no-SSH DirectAdmin deployment guide](docs/deployment.md).

Run the checks:

```bash
composer test
vendor/bin/pint --test
npm run build
```

Some Windows PHP distributions omit PDO SQLite. In that case enable `pdo_sqlite` and `sqlite3` for the test command, or use the CI workflow.

## Codex plugin

The private-beta plugin is configured for `https://curator.vumbualabs.com/mcp`. Install the repo-local marketplace, install `timeline-curator`, authenticate its MCP server as the current user, and create personal schedules using the prompt in `plugins/timeline-curator/assets/scheduled-task-prompt.md`.

For DirectAdmin hosting without SSH, build the complete upload ZIP locally with `scripts/build-directadmin-release.ps1`. It includes production dependencies, compiled assets, generated application secrets, and the disabled-by-default one-time database installer.

Recommended schedules are 07:00 and 18:00 in each user's timezone. Each run is a fresh user-owned task; the Timeline policy API is the durable learning state.

## Security invariants

- MCP and ingestion inputs never accept `tenant_id`.
- Access tokens are opaque, stored only as hashes, checked for expiry and tool scope, and resolved directly to their owning user.
- Tenant context is cleared after every request.
- Tenant-bound inserts overwrite any caller-supplied tenant value.
- Source pages are not fetched by the backend; only minimal evidence metadata is stored.
- HTTPS, private-address, quota, hard-rule, evidence-coverage, and idempotency checks run before publication.

The MCP contract and rejection codes are documented in [docs/mcp-contract.md](docs/mcp-contract.md).
