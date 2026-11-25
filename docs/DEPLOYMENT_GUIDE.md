# ZENTRIX ASTRA Deployment Guide

## Deployment status: blocked pending host verification

No deployment has been attempted. The name **“Etopia Deploy”** did not identify an unambiguous hosting product with official application-hosting documentation. Searches of the exact name and related PHP/Laravel terms did not locate an official deployment guide. The official [Etopia website](https://etopia.eco/) and its [technical downloads](https://etopia.eco/technical-downloads/) describe building-system materials, not application hosting. This does not prove that the intended hosting service is unsupported; it means its capabilities cannot currently be verified.

Do not treat a hosting dashboard or a similarly named product as evidence of compatibility. Before deployment, obtain the exact vendor product URL and plan name, then verify every capability below against that vendor's current official docs or support response.

### Hosting credentials

The required Etopia account/deployment credential type, scope, and configuration screen cannot be determined until the exact product is identified. Do not create or share a token based on guesswork. After the official product is confirmed, use its documented integration/deploy-token flow, grant only the permissions needed to deploy this repository, and keep the token in the provider's integration/secret store—not in GitHub files or chat. Database credentials, `APP_KEY`, SMTP credentials, and optional AI credentials belong in the application's private runtime environment settings described in [Environment Setup](./ENVIRONMENT_SETUP.md).

| Requirement | Application requirement | Etopia Deploy status |
|---|---|---|
| PHP | `composer.json` requires PHP `^8.2`; use a maintained PHP version compatible with Laravel 12. | Not verified |
| Laravel | Laravel framework `^12.0`. | Not verified |
| PHP extensions | At minimum, the Laravel 12 deployment extensions plus `pdo_mysql`; this app also uses cURL and Fileinfo. See Laravel's [server requirements](https://laravel.com/docs/12.x/deployment#server-requirements). | Not verified |
| MySQL | MySQL-compatible production database with InnoDB, `utf8mb4`, and the PHP PDO MySQL driver. All 15 migrations ran on local XAMPP MariaDB 10.4.32; production provider version, TLS, and migration behavior remain unverified. | Production not verified |
| Composer | Must allow locked Composer dependencies to be installed with development packages excluded. | Not verified |
| Web server | Must route requests to `public/index.php`, use `public/` as the document root, and deny access to project files outside it. | Not verified |
| Storage | `storage/` and `bootstrap/cache/` must be writable by the PHP process. `storage/app/private/file-checks` must be durable across restarts and releases. | Not verified |
| Environment variables | Must provide application configuration and private secrets without committing or publicly serving an `.env` file. | Not verified |
| HTTPS | Must provide a trusted certificate and enforce HTTPS at the edge. | Not verified |
| Queue worker | Must run a persistent Laravel database queue worker for scanner jobs. Redis is not required by the current design. | Not verified |
| Scheduler | Must run `php artisan schedule:run` every minute for retention/pruning tasks. | Not verified |

### Host and external-service prerequisites

1. A verified Etopia application runtime meeting the requirements above.
2. A production MySQL database and dedicated database user. Use the provider's TLS option and least-privilege permissions where available.
3. Persistent private file storage for `storage/app/private/file-checks`. The application currently uses a local private disk for File Check; it does not switch that disk to S3 using `FILESYSTEM_DISK`. If the host's local filesystem is ephemeral or deployments use multiple instances, **do not deploy File Check until a durable shared private volume is confirmed or private-object-storage support is deliberately implemented and tested**.
4. A managed worker process for Laravel's database queue and a scheduler/cron facility. The scheduler includes retention/pruning tasks; OpenPhish refresh is no longer scheduled.
5. A mail provider for password-reset delivery. The template's default `MAIL_MAILER=log` is for development only; it writes reset messages to logs.
6. A domain and HTTPS certificate. Host validation allows the hostname in `APP_URL` plus `127.0.0.1` and `localhost` for normal requests. Laravel's liveness endpoint `/up` accepts internal probe hostnames and avoids database-backed web sessions. Configure the platform health-check path as `/up`; do not use `/` as the health check. Other application routes retain trusted-host validation.
7. Optional external integrations are not launch prerequisites. OpenRouter uses free routing only and may be limited by model availability/rate limits. VirusTotal is optional. OpenPhish is retired from current runtime behavior.

### Release procedure

Do not run these commands against a live environment until the database, secret store, persistent storage, and recovery plan are confirmed. Back up the database and private files before schema changes.

1. **Build a release artifact.** In CI or an approved build environment, install locked production PHP dependencies and compile assets:

   ```sh
   composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
   npm ci
   npm run build
   ```

   The build must include `public/build/manifest.json` and its assets. `public/build/` is Git-ignored, so either the platform must build it or the release artifact must include it. Node is not required at runtime if the compiled artifacts are deployed.

2. **Configure runtime secrets in the host's secret manager.** Follow [Environment Setup](./ENVIRONMENT_SETUP.md). Never upload `.env`, API keys, database passwords, or generated `APP_KEY` values to GitHub.

3. **Configure the web root and HTTPS.** Set the document root to the release's `public/` directory. Enable HTTPS and HTTP-to-HTTPS redirection at the host. Do not expose the repository root, `storage/`, `vendor/`, or `.env`.

4. **Grant least-privilege filesystem access.** The PHP process must write to `storage/` and `bootstrap/cache/`. Persist `storage/app/private/file-checks` outside ephemeral release directories. Do not publish or symlink this private directory into `public/`.

5. **Run schema migrations deliberately.** After a successful backup and verified database connection:

   ```sh
   php artisan migrate --force
   ```

   The migration set includes users/sessions, cache, database queues, scanner results, private file records, AI conversation records, Academy content/progress, Safe Navigation history, and platform audit events. Local XAMPP MariaDB migration has been verified; back up and verify the actual production database before running migrations there.

6. **Seed original Academy material if desired.** The seeder creates local original learning content and no administrator credentials:

   ```sh
   php artisan db:seed --force
   ```

7. **Create the first administrator from a trusted interactive console.** There is no default admin account:

   ```sh
   php artisan zentrix:bootstrap-admin
   ```

   Use the hidden password prompt and do not place the password in a command argument or shell history. This command can create only the first administrator. If the host cannot provide a secure interactive console, resolve that operational requirement before launch.

8. **Optimize after production variables are available to the process.**

   ```sh
   php artisan optimize
   ```

   Rebuild the cached configuration after changing environment variables. Restart long-running workers after each release; Laravel documents `queue:restart` for this purpose.

9. **Run the database queue worker as a managed persistent process.**

   ```sh
   php artisan queue:work database --sleep=3 --tries=1 --timeout=45
   ```

   The scanner job timeout is 35 seconds and the database queue `retry_after` is 90 seconds. Monitor failed jobs and worker restarts. A web request alone does not process queued scans.

10. **Run scheduled retention tasks every minute.** Configure one host scheduler entry:

    ```cron
    * * * * * cd /path/to/release && php artisan schedule:run >> /dev/null 2>&1
    ```

    Confirm `php artisan schedule:list` includes File Check, Safe Navigation, AI thread, and platform-audit pruning tasks.

11. **Verify before opening registration.** Test `/up` and `/health/ready`, sign-in, password-reset email delivery, administrator bootstrap, a queued authorized scan, private file upload/read/delete, logs, scheduled cleanup, and a backup restore in a non-production environment. Confirm ordinary users cannot reach administrator routes. Then decide whether public registration should remain enabled or set `AUTH_ALLOW_REGISTRATION=false`.

### Production checks and maintenance

- Keep `APP_DEBUG=false`, `APP_ENV=production`, `APP_URL=https://<canonical-host>`, and enforce HTTPS at the host.
- Check the readiness endpoint after database migrations and after each deployment. It tests database readiness, not general security.
- Monitor worker liveness, queue depth, failed jobs, storage capacity, database health, and error logs.
- Use encrypted off-site backups for MySQL and the private File Check storage. Test restoration regularly; database backups alone do not include locally stored private files.
- Schedule dependency/security updates, review admin/audit events, and verify pruning tasks run. Current retention periods are 30 days for File Check records, Safe Navigation records, and inactive AI threads, and 365 days for platform audit events.
- Never treat a configured key as proof of production readiness. VirusTotal reputation checks are implemented, but deployment still requires provider-term approval and production connectivity verification. OpenRouter is restricted to free routing with paid fallbacks disabled; a real inference acceptance test is required because key authentication does not guarantee current model availability.

### Official references

- [Etopia](https://etopia.eco/) and [Etopia technical downloads](https://etopia.eco/technical-downloads/) — reviewed, but not hosting documentation for an identified “Etopia Deploy” product.
- Laravel 12 [deployment](https://laravel.com/docs/12.x/deployment), [queues](https://laravel.com/docs/12.x/queues), [task scheduling](https://laravel.com/docs/12.x/scheduling), and [file storage](https://laravel.com/docs/12.x/filesystem).
- [PHP supported versions](https://www.php.net/supported-versions.php) and Composer [`check-platform-reqs`](https://getcomposer.org/doc/03-cli.md#check-platform-reqs).
