# DirectAdmin deployment without SSH

Production host: `https://curator.vumbualabs.com`

- Application directory: `/domains/curator.vumbualabs.com/app`
- Document root: `/domains/curator.vumbualabs.com/app/public`
- One-time installer: `https://curator.vumbualabs.com/deployment/install`

## 1. Prepare DirectAdmin

1. Enable SSL for `curator.vumbualabs.com`.
2. Select PHP 8.3 or newer and enable mbstring, OpenSSL, PDO MySQL, tokenizer, XML, ctype, JSON, fileinfo, curl, and zip.
3. Create an empty MySQL database and a dedicated database user.
4. Create `/domains/curator.vumbualabs.com/app`.
5. Set the subdomain document root to `/domains/curator.vumbualabs.com/app/public`.

Keep `.env` and the application root outside the public document root.

## 2. Build and upload

Run locally:

```powershell
.\scripts\build-directadmin-release.ps1
```

Upload `dist/curator-vumbualabs-directadmin.zip` into the application directory and extract it. Edit only the `REPLACE_WITH_...` database values in `.env`; there are no Auth0 values. Make `storage` and `bootstrap/cache` writable, normally mode 775.

## 3. Run the web installer

Open the installer URL, submit the token from `dist/curator-vumbualabs-deployment-secrets.txt`, and wait for migration completion. Then set `WEB_INSTALLER_ENABLED=false` and clear `WEB_INSTALLER_TOKEN_HASH`.

For the existing production database, the migration preserves every tenant, topic, directive, story, and feedback record. Enter the existing owner's email and a new 12+ character password in the install form; the installer updates that account in place. Leave those optional fields blank for a fresh database, then use `/register`.

## 4. Verify

1. `/up` reports healthy.
2. `/.well-known/oauth-protected-resource/mcp` names `https://curator.vumbualabs.com/mcp`.
3. `/.well-known/oauth-authorization-server` exposes the local authorize, token, and registration endpoints and lists `S256`.
4. `/register`, `/login`, and `/logout` work.
5. Reinstall Timeline Curator, approve it on the Timeline page, and run one on-demand cycle.

No hosting cron, queue worker, Auth0 tenant, terminal, or SSH is required. Scheduled curation remains a user-owned Codex task.
