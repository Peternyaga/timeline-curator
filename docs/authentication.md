# First-party Timeline authentication

Timeline is both the user account system and the OAuth authorization server used by Codex. Auth0 is not part of the request path.

## User flow

1. Create an account at `/register` with an email address and a password of at least 12 characters.
2. Configure topics, directives, and feedback in `/timeline`.
3. Install Timeline Curator. Its MCP URL is `https://curator.vumbualabs.com/mcp`.
4. Codex discovers the protected-resource and authorization-server metadata, dynamically registers its public callback, and opens `/oauth/authorize`.
5. Sign into Timeline and approve the requested scopes.
6. Timeline sends a one-use, five-minute authorization code to Codex.
7. Codex exchanges the code at `/oauth/token` using S256 PKCE.
8. MCP requests send the opaque access token in the `Authorization: Bearer …` header.

The access-token row points to a Timeline user. The server loads that user's tenant; neither OAuth nor MCP accepts `tenant_id` from the client.

## Security properties

- Public clients have no client secret and must use S256 PKCE.
- Registration is idempotent for the same client name and callback set. It creates a lightweight local database record, not an external OAuth application.
- Redirect URLs must be HTTPS, except HTTP loopback callbacks for native Codex clients.
- Authorization codes are stored as SHA-256 hashes, expire after five minutes, and can be consumed once.
- Access and refresh tokens are opaque random values stored only as SHA-256 hashes.
- Access tokens expire after 60 minutes.
- Refresh credentials are reusable and remain valid until the user revokes the underlying authorization grant. This avoids refresh races between scheduled and interactive Codex sessions.
- Users can inspect last contact and renewal times or revoke an installation from `/connections`.
- Only the three Timeline MCP scopes can be requested.

TTL values can be adjusted with `OAUTH_CODE_TTL_MINUTES`, `OAUTH_ACCESS_TOKEN_TTL_MINUTES`, and `OAUTH_REFRESH_TOKEN_TTL_DAYS`.
`OAUTH_REFRESH_TOKEN_UNTIL_REVOKED=true` is the default and takes precedence over the legacy refresh TTL, including on existing deployments whose `.env` still contains `OAUTH_REFRESH_TOKEN_TTL_DAYS=30`. Set the switch to `false` and provide a positive TTL only when an inactivity expiry is explicitly required.

Bearer failures include an OAuth `invalid_request` or `invalid_token` challenge and the protected-resource metadata URL. Timeline records secret-free grant, refresh, rejection, and revocation events; raw tokens are never logged.

The `auth0_sub` database column remains nullable only to preserve existing production rows during migration. It is not read by the authentication flow.
