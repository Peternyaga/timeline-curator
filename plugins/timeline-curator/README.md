# Timeline Curator Codex plugin

This private-beta plugin connects a user's personal Codex task to the remote Timeline MCP server using OAuth. Each installation has independent credentials and can be scheduled independently.

Before distribution, replace `timeline.example.com` in `.mcp.json` with the deployed HTTPS subdomain. Configure Auth0 as the authorization server for that resource and grant the three scopes declared in the plugin.

The plugin does not contain a crawler or an OpenAI API integration. The Codex task performs research with its available tools and submits only validated cluster metadata to Timeline.
