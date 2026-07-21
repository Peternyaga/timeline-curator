# Auth0 configuration

Create two Auth0 resources in the same tenant:

1. A Regular Web Application for browser login. Enable Authorization Code with PKCE and configure:
   - callback: `https://curator.vumbualabs.com/auth/callback`
   - logout: `https://curator.vumbualabs.com/`
   - web origin: `https://curator.vumbualabs.com`
2. An API whose identifier exactly matches `AUTH0_AUDIENCE`: `https://curator.vumbualabs.com/mcp`. Enable RBAC permissions in access tokens.

Add these API permissions:

- `read:curation-context`
- `write:curation-runs`
- `write:story-batches`

Configure Auth0's OAuth 2.1/Dynamic Client Registration support for third-party MCP clients. Allow only the Codex callback patterns required for your rollout, require Authorization Code + PKCE, and do not enable password or client-credentials grants for end users.

Set:

```dotenv
AUTH0_DOMAIN=tenant-region.auth0.com
AUTH0_CLIENT_ID=<regular-web-app-client-id>
AUTH0_CLIENT_SECRET=<regular-web-app-client-secret>
AUTH0_COOKIE_SECRET=<64-or-more-random-hex-characters>
AUTH0_AUDIENCE=https://curator.vumbualabs.com/mcp
```

The protected-resource metadata is published at `/.well-known/oauth-protected-resource`. The MCP plugin requests all three permissions, but the server checks the permission required by each tool independently.
