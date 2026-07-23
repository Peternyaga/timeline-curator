# Timeline Curator external tester guide

Timeline Curator is an early public beta. The plugin connects Codex to your own Timeline account, reads only your configured policy and feedback, researches with Codex's available web tools, and publishes accepted story clusters back to your private feed.

## What you need

- Codex Desktop or the Codex CLI
- A free Timeline account at [curator.vumbualabs.com](https://curator.vumbualabs.com)
- A browser that can return to Codex's temporary localhost OAuth callback

Every tester authenticates independently. Timeline resolves the bearer token to the signed-in user and derives the tenant internally; the plugin never asks for or sends a tenant ID.

## 1. Add the Vumbua Labs marketplace

In Codex Desktop, open **Settings → Plugins → Marketplaces**, choose **Add marketplace**, and enter:

```text
Peternyaga/timeline-curator
```

If the marketplace screen is unavailable, use the CLI.

### Windows PowerShell

If `codex` is already on your `PATH`:

```powershell
codex plugin marketplace add Peternyaga/timeline-curator
codex plugin add timeline-curator@vumbua-labs
```

For a standard npm installation where PowerShell cannot find `codex`:

```powershell
& "$env:APPDATA\npm\codex.cmd" plugin marketplace add Peternyaga/timeline-curator
& "$env:APPDATA\npm\codex.cmd" plugin add timeline-curator@vumbua-labs
```

### macOS or Linux

```bash
codex plugin marketplace add Peternyaga/timeline-curator
codex plugin add timeline-curator@vumbua-labs
```

Restart Codex Desktop or open a new Codex task after installation so the Timeline tools and skill are loaded.

## 2. Create and configure your Timeline account

1. Open [curator.vumbualabs.com/register](https://curator.vumbualabs.com/register).
2. Create an account and sign in.
3. Add at least one focused topic.
4. Add any hard directives the curator must follow.
5. Leave feedback on delivered stories over time; this becomes your personal learning signal.

The curator intentionally returns an empty result when you have no active topics. It does not invent a generic feed.

## 3. Authenticate Codex

Installation should start authentication automatically. If it does not, run:

```powershell
& "$env:APPDATA\npm\codex.cmd" mcp login timeline
```

On macOS, Linux, or a Windows installation with `codex` on `PATH`, run:

```bash
codex mcp login timeline
```

Codex opens Timeline's authorization page. Sign in to your Timeline account, review the requested access, and approve it. Timeline returns a short-lived authorization code to Codex, which exchanges it using S256 PKCE. Subsequent MCP requests carry an opaque bearer token.

Do not share access tokens, authorization codes, or localhost callback URLs.

## 4. Run the first curation cycle

Start a new Codex task and use:

```text
Run one complete Timeline curation cycle and monitor it through completion.
```

The task should:

1. Load and pin your current curation context.
2. Research your active topics using credible, inspected sources.
3. Start one curation run with the exact research queries.
4. Submit only evidence-backed clusters with exactly three technical bullets.
5. Complete the run, including a valid empty completion when nothing clears the policy.

Review the result at [curator.vumbualabs.com/timeline](https://curator.vumbualabs.com/timeline), then score stories and leave comments to refine later runs.

## 5. Update the plugin

```bash
codex plugin marketplace upgrade vumbua-labs
codex plugin add timeline-curator@vumbua-labs
```

Open a new Codex task after updating.

## Troubleshooting

### Authentication does not open

Run `codex mcp logout timeline`, then `codex mcp login timeline`. Restart Codex Desktop if an old task still holds stale connector state.

### The browser reaches localhost but does not return to Codex

Keep Codex running during authorization. Allow the temporary `127.0.0.1` callback through local browser or endpoint-security controls, then retry login. The callback port changes between attempts.

### `get_curation_context` returns no topics

Sign in to Timeline and create at least one active topic before rerunning the task.

### An MCP tool reports an internal error

Record the tool name, approximate time, and visible error message. Do not post tokens or complete callback URLs. Retry once in a new task; if it persists, report it in [GitHub Issues](https://github.com/Peternyaga/timeline-curator/issues).

### Reinstall cleanly

```bash
codex plugin remove timeline-curator@vumbua-labs
codex plugin marketplace upgrade vumbua-labs
codex plugin add timeline-curator@vumbua-labs
codex mcp login timeline
```

## Sending useful beta feedback

Open a [GitHub issue](https://github.com/Peternyaga/timeline-curator/issues) with:

- operating system and Codex version
- whether installation, authentication, context loading, run creation, or publication failed
- the MCP tool name and sanitized error
- what you expected to happen

Never include passwords, bearer tokens, authorization codes, session cookies, or full OAuth callback URLs.
