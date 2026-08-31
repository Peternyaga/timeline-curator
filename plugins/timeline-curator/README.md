# Timeline Curator Codex plugin

This public-beta plugin connects a user's personal Codex task to the remote Timeline MCP server using OAuth. It curates both stories and profile-matched jobs, and it processes only applications the user approves one at a time.

The plugin connects to `https://curator.vumbualabs.com/mcp`. Codex discovers Timeline's own authorization server, opens its login and consent page, and uses Authorization Code with S256 PKCE. No Auth0 tenant or per-user OAuth application is required.

The plugin does not contain a crawler or an OpenAI API integration. The Codex task performs broad, topic-appropriate research with its available tools, runs a dedicated embeddable-media check for every candidate, and submits only validated cluster, source, media-reference, and feedback-choice metadata to Timeline.

Version 0.5 adds the private Jobs workspace, combined story/job runs, job feedback, and an evidence-gated application queue. Upgrading users reconnect once for the additional job/application scopes.

Version 0.5.1 makes scheduled authorization resilient. Timeline access tokens remain short-lived, while one reusable refresh credential keeps the approved connection active across daily, weekly, interactive, and concurrent tasks until the user revokes it. Timeline is optional during general Codex startup: an outage or expired legacy credential stops only a Timeline cycle and never blocks unrelated Codex tasks.

See the repository's [external tester setup guide](../../docs/external-testing.md) for installation, authentication, first-run, update, and troubleshooting instructions.
