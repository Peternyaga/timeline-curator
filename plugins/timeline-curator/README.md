# Timeline Curator Codex plugin

This public-beta plugin connects a user's personal Codex task to the remote Timeline MCP server using OAuth. Each installation has independent credentials and can be scheduled independently.

The plugin connects to `https://curator.vumbualabs.com/mcp`. Codex discovers Timeline's own authorization server, opens its login and consent page, and uses Authorization Code with S256 PKCE. No Auth0 tenant or per-user OAuth application is required.

The plugin does not contain a crawler or an OpenAI API integration. The Codex task performs broad, topic-appropriate research with its available tools, runs a dedicated embeddable-media check for every candidate, and submits only validated cluster, source, media-reference, and feedback-choice metadata to Timeline.

See the repository's [external tester setup guide](../../docs/external-testing.md) for installation, authentication, first-run, update, and troubleshooting instructions.
