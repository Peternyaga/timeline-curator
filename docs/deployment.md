# HOSTAFRICA shared-hosting deployment

## Requirements

- PHP 8.3 or newer with mbstring, OpenSSL, PDO MySQL, tokenizer, XML, ctype, JSON, and fileinfo
- MySQL 8-compatible database
- HTTPS on one subdomain
- The subdomain document root pointed to this repository's `public` directory

## Release procedure

1. Build vendor and frontend artifacts in CI or a PHP 8.3 build environment: `composer install --no-dev --optimize-autoloader` and `npm ci && npm run build`.
2. Upload the repository excluding `.env`, development caches, `node_modules`, and tests if desired. Include `vendor` and `public/build` when the shared host cannot build them.
3. Create a production `.env` with `APP_ENV=production`, `APP_DEBUG=false`, HTTPS `APP_URL`, MySQL credentials, and Auth0 settings.
4. Make `storage` and `bootstrap/cache` writable by the PHP process.
5. Run `php artisan migrate --force`, `php artisan config:cache`, `php artisan route:cache`, and `php artisan view:cache`.
6. Verify `/up`, `/.well-known/oauth-protected-resource`, browser login, and an authenticated MCP initialization request.

No host cron or application queue is required for curation. Scheduling belongs to each user's Codex account. If the beta approaches shared-hosting resource limits, migrate the same application and MySQL schema to a VPS before expanding beyond the planned cohort.
