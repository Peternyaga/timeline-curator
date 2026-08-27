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

The durable-authentication migration preserves currently valid refresh credentials and converts them to reusable, until-revoked grants. Credentials that expired before deployment require one final OAuth login. The update also adds user-scoped product-update read state.

## 4. Verify

1. `/up` reports healthy.
2. `/.well-known/oauth-protected-resource/mcp` names `https://curator.vumbualabs.com/mcp`.
3. `/.well-known/oauth-authorization-server` exposes the local authorize, token, and registration endpoints and lists `S256`.
4. `/register`, `/login`, and `/logout` work.
5. Reinstall Timeline Curator, approve it on the Timeline page, and run one on-demand cycle.

No hosting cron, queue worker, Auth0 tenant, terminal, or SSH is required. Scheduled curation remains a user-owned Codex task.

## Updating an existing installation

Build an update archive that deliberately excludes `.env`:

```powershell
.\scripts\build-directadmin-release.ps1 -ExistingDeployment
```

Upload `dist/curator-vumbualabs-update.zip` to `/domains/curator.vumbualabs.com/app` and extract it over the existing application. The archive includes production dependencies and compiled frontend assets but cannot overwrite the production database credentials, application key, sessions, or OAuth token configuration because it contains no `.env` file.

After extraction, open `https://curator.vumbualabs.com/deployment/install` and enter the one-time token from `dist/curator-vumbualabs-update-token.txt`. The update package stores only the token hash outside the public document root. A successful migration deletes that hash automatically, closes the update endpoint, and preserves the existing `.env`, tenants, users, OAuth credentials, stories, and feedback.

The installer briefly enables Laravel maintenance mode, clears stale framework caches, runs migrations, rebuilds the production config/route/event/view caches against the server's preserved `.env`, and resets OPcache when the host permits it.

For best production performance, enable PHP OPcache in DirectAdmin with at least 128 MB, about 20,000 cached scripts, and 16 MB of interned strings. Keep JIT disabled for this request-oriented application. On shared hosting, retain timestamp validation with a short revalidation interval unless the deployment process can reliably reload PHP-FPM.

If DirectAdmin Terminal is available, `php artisan migrate --force` from the application directory is an equivalent migration path. Do not activate schema-dependent application code without completing one of these migration steps.
