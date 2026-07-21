# DirectAdmin deployment without SSH

Production host: `https://curator.vumbualabs.com`

- Application directory: `/domains/curator.vumbualabs.com/app`
- Document root: `/domains/curator.vumbualabs.com/app/public`
- One-time installer: `https://curator.vumbualabs.com/deployment/install`

## 1. Prepare DirectAdmin

1. Enable SSL for `curator.vumbualabs.com`.
2. Select PHP 8.3 or newer and enable mbstring, OpenSSL, PDO MySQL, tokenizer, XML, ctype, JSON, fileinfo, curl, and zip.
3. Create an empty MySQL database and a dedicated user with all privileges on that database.
4. Create `/domains/curator.vumbualabs.com/app` in File Manager.
5. Change the subdomain document root from `/domains/curator.vumbualabs.com/public_html` to `/domains/curator.vumbualabs.com/app/public`.

Keep the application root and `.env` outside the public document root. Do not point the domain at `/domains/curator.vumbualabs.com/app`.

## 2. Build the upload package locally

From PowerShell in the repository:

```powershell
.\scripts\build-directadmin-release.ps1
```

The script creates two ignored files under `dist`:

- `curator-vumbualabs-directadmin.zip`, containing production Composer dependencies, compiled frontend assets, and a generated production `.env`.
- `curator-vumbualabs-deployment-secrets.txt`, containing the ZIP checksum and one-time installer token. Never upload or share this secrets file.

The ZIP's `.env` already contains generated application and cookie secrets. Before installation, edit only the `REPLACE_WITH_...` database and Auth0 values.

## 3. Upload and configure

1. Upload the ZIP into `/domains/curator.vumbualabs.com/app` using DirectAdmin File Manager.
2. Extract it directly into that directory; `artisan`, `vendor`, `public`, and `.env` should be immediate children of `app`.
3. Delete the uploaded ZIP from the server.
4. Edit `/domains/curator.vumbualabs.com/app/.env` and replace all database and Auth0 placeholders.
5. Set `storage` and `bootstrap/cache` to writable permissions (normally 775) using File Manager.

## 4. Install the database once

1. Visit `https://curator.vumbualabs.com/deployment/install`.
2. Enter the one-time token from the local secrets file.
3. Wait for the confirmation page. The installer creates a lock file and refuses subsequent runs.
4. Immediately edit the server `.env`: set `WEB_INSTALLER_ENABLED=false` and clear `WEB_INSTALLER_TOKEN_HASH`.

If installation fails, inspect DirectAdmin's PHP error log, confirm the database values and PHP extensions, and retry. Do not enable `APP_DEBUG` on the public server.

## 5. Verify and connect Codex

1. Confirm `https://curator.vumbualabs.com/up` returns healthy.
2. Confirm `https://curator.vumbualabs.com/.well-known/oauth-protected-resource` returns the Auth0 issuer and Timeline MCP resource.
3. Test browser login and logout.
4. Reinstall the updated Timeline Curator plugin, authenticate it as the current user, and run one on-demand curation cycle before creating the 07:00 and 18:00 personal schedules.

No hosting cron, queue worker, terminal, or SSH access is required. Curation schedules remain user-owned Codex tasks.
