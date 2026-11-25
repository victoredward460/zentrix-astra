# ZENTRIX ASTRA Environment Setup

## Secret-handling rules

- Do not paste credentials into chat, screenshots, source code, issue trackers, or this document.
- Set production values in the hosting platform's private environment/secret manager. Never commit `.env` or private credentials to GitHub.
- `.gitignore` now ignores `.env*` while explicitly allowing the safe `.env.example` template. This is a safeguard, not a substitute for checking Git status before pushing.
- The actual local `.env` was not read or modified during Phase 13.
- If a secret is accidentally committed, deleting the file is insufficient: revoke/rotate the credential and remove it from repository history using the owner's approved process.

## Required production variables

Set these in the hosting platform's secret/configuration UI. The host's exact variable syntax and whether it supports multiline/secret values remain unverified until the Etopia product is identified.

| Variable | Production value |
|---|---|
| `APP_NAME` | `ZENTRIX ASTRA` |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://` plus the one canonical public hostname |
| `APP_KEY` | A new Laravel-generated key stored only as a secret |
| `DB_CONNECTION` | `mysql` |
| `DB_HOST`, `DB_PORT` | Values from the managed database provider |
| `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Dedicated production database/schema and least-privilege account values |
| `MYSQL_ATTR_SSL_CA` | Provider CA file/path when required for MySQL TLS; this is the variable read by the current MySQL connection configuration |
| `SESSION_DRIVER` | `database` |
| `SESSION_ENCRYPT` | `true` |
| `SESSION_SECURE_COOKIE` | `true` |
| `SESSION_HTTP_ONLY` | `true` |
| `SESSION_SAME_SITE` | `lax` |
| `CACHE_STORE` | `database` |
| `QUEUE_CONNECTION` | `database` |
| `LOG_CHANNEL` | Prefer the platform's captured `stderr` channel or another monitored production sink |
| `LOG_LEVEL` | `warning` or the organization's monitored production level; do not use verbose debug logging by default |
| `FILESYSTEM_DISK` | `local` for the current supported configuration; keep private File Check storage on durable storage |

The current session configuration defaults `SESSION_SECURE_COOKIE` to true when `APP_ENV=production`; set it explicitly to `true` as well. If the production site is not HTTPS, stop and fix TLS rather than disabling the secure-cookie flag.

### Generate and store `APP_KEY`

Generate a key in a trusted local shell or deployment console:

```sh
php artisan key:generate --show
```

Copy the output directly into the host's secret field. Do not send it here, put it in `.env.example`, or add it to a deployment file. Keep the same key across normal releases. Rotating it can invalidate encrypted sessions and other encrypted data; plan rotation and use Laravel's `APP_PREVIOUS_KEYS` only when an intentional rotation is required.

### Database setup

Provision a production MySQL-compatible database and a dedicated user with only the permissions needed to run migrations and application queries. Require TLS if the service supports it. The app expects the PDO MySQL extension, uses `utf8mb4`, and configures strict mode. OpenPhish feed tables remain in the historical migration/schema for compatibility; the application no longer refreshes or queries them.

Do not use the SQLite test database or development credentials for production. Configure and validate production environment settings before running `php artisan migrate --force`.

### Password-reset email

The development default is `MAIL_MAILER=log`; this is **not suitable for production**, because reset links are written to logs. Select a mail provider and enter its credentials only in the host secret manager. The current Laravel config reads:

- `MAIL_MAILER=smtp`
- `MAIL_SCHEME` (provider-specific TLS/SSL scheme)
- `MAIL_HOST`, `MAIL_PORT`
- `MAIL_USERNAME`, `MAIL_PASSWORD`
- `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`

Verify the provider's TLS mode, sender/domain verification, rate limits, delivery events, cost, and data-processing terms using its official documentation. Send a test reset email before launch.

### Optional integrations

These are not required to launch the locally implemented modules:

- **AI Agent:** the shared AI adapter uses `OPENROUTER_API_KEY` and defaults `OPENROUTER_MODEL` to `nvidia/nemotron-3-ultra-550b-a55b:free`. Users can choose either that or `nvidia/nemotron-3.5-super-vl:free` in the AI chat. Local configuration temporarily sets `OPENROUTER_REQUIRE_ZDR=false` because current metadata does not establish strict-ZDR eligibility for the free NVIDIA endpoints, and `OPENROUTER_DATA_COLLECTION=allow` following explicit owner authorization. The latter permits NVIDIA to retain and use prompts for training under its policy; `.env.example` keeps the safer `deny` default. Requests remain NVIDIA-only, free-only, no-fallback, and zero-price. AI sharing requires one saved, revocable consent per account, not per-message agreement.
- **Academy materials:** Admins can generate downloadable HTML/PDF study guides. The app stores files on a private disk, authorizes owner downloads, and creates stable signed share URLs. Deleting a guide revokes its public link. No new package or paid service is required.
- **Safe Navigation reputation:** VirusTotal remains optional and requires `VIRUSTOTAL_API_KEY`; do not assume configuration alone confirms connectivity. OpenPhish has been retired and has no environment setting or active code path.
- **Platform Controls:** Administrators can pause ordinary-user module access and public registration from the authenticated admin page. The environment value `AUTH_ALLOW_REGISTRATION=false` remains a hard ceiling that the admin UI cannot override. Feature toggles and registration must be checked after deployment, and changes are written to the platform audit trail.
- **AWS/S3:** Laravel's generic `s3` disk exists in configuration, but the File Check service currently writes to the named local `file-checks` disk. AWS variables alone will not move private uploads to S3.

### Build and runtime configuration

- Build assets with `npm ci && npm run build`; the installed Vite 7 package requires Node `^20.19.0 || >=22.12.0`. Deploy the resulting `public/build` files. Node may be omitted from the runtime image after the build.
- Install production PHP dependencies from `composer.lock`: `composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction`.
- Use `php artisan optimize` only after production variables are available in the process. Re-run the appropriate cache commands and restart workers after configuration changes.
- Keep `storage/` and `bootstrap/cache/` writable by the PHP service account. Persist `storage/app/private/file-checks` across restarts and releases.
- `APP_URL` anchors trusted-host validation for application routes; `127.0.0.1` and `localhost` are also permitted. Public GET/HEAD requests to `/` and the generic liveness endpoint `/up` bypass host validation so internal platform probes work. Set the deployment health-check path to `/up`; other routes retain the host allowlist.
- Configure the host to terminate HTTPS, redirect HTTP to HTTPS, and correctly communicate the external scheme to PHP. Do not trust arbitrary forwarded headers; use the host's documented trusted-proxy settings if application-level proxy trust becomes necessary.

For platform requirements and the release process, see [Deployment Guide](./DEPLOYMENT_GUIDE.md). For missing platform capabilities and non-launch-ready items, see [Known Limitations](./KNOWN_LIMITATIONS.md).
