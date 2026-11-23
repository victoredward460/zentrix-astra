# ZENTRIX ASTRA — Final Project Documentation

> Historical project snapshot. Since this report was written, OpenPhish runtime behavior was retired and File Check gained consent-based AI explanations. See [Development Status](./DEVELOPMENT_STATUS.md) and [API Requirements](./API_REQUIREMENTS.md) for current behavior.

## Project status

ZENTRIX ASTRA is a Laravel 12 defensive-security platform foundation with authenticated, account-scoped modules. The configured local XAMPP MariaDB 10.4.32 database has all 15 project migrations applied, including the additive OpenPhish feed tables. The current full suite passes with 106 tests and 774 assertions; the frontend production build and targeted changed-file formatting checks pass. **The project is not declared ready for launch and has not been deployed.** The intended “Etopia Deploy” host could not be identified unambiguously from official public documentation, and production database hosting, persistent private storage, queue workers, scheduler, HTTPS, and mail delivery have not been verified on a real host.

## Architecture

- **Backend:** PHP `^8.2`, Laravel `^12.0`, Blade, session authentication, policies/middleware, Eloquent.
- **Database:** MySQL-compatible MariaDB is used by the local XAMPP database. All 15 migrations have been run there. SQLite remains isolated to feature tests; production database version, TLS, backups, and restore are unverified.
- **Frontend:** Vite 7, Tailwind CSS, vanilla JavaScript, and Blade templates. Local Oxanium and Inter font assets, redesigned Z mark, shared short brand intro, and restrained glass surfaces are compiled with the production assets.
- **Asynchronous work:** Laravel database queue. Scanner jobs require a separately managed worker.
- **Scheduled work:** Laravel scheduler prunes expired File Check records, Safe Navigation history, AI threads, and platform audit events; the OpenPhish refresh command is scheduled twice daily.
- **Files:** File Check stores private uploads under the local `file-checks` disk (`storage/app/private/file-checks`), stores metadata and SHA-256, and never executes uploaded content. It does not provide an antivirus/malware verdict.
- **External services:** OpenPhish Community Feed is disabled by default pending terms approval, and no live feed download has been verified. No live external AI or credentialed URL-reputation provider is configured. Password reset uses Laravel's `log` mailer by default and needs a real mail provider before production delivery.

## Completed functionality

| Module | Current behavior | Verification/status |
|---|---|---|
| Authentication and profiles | Registration creates ordinary accounts; login/logout, password reset workflow, password change, profile update, and rate limits. | Feature tests pass. Production email delivery is not configured. |
| Roles and administration | Administrator bootstrap command, user role/status controls, protected routes, owner-linked reports, minimized audit events, and audited switches for registration and five user-facing modules. | Feature tests cover authorization, the environment registration lock, pause enforcement, audit events, and administrator recovery access. No production administrator is created yet. |
| Security Dashboard | Displays account-owned database activity and explicit empty/unavailable states; does not invent metrics or security scores. | Feature tests pass on SQLite. |
| Core Scanner | Requires authorization and DNS verification; executes bounded passive checks via a queued lifecycle and saves evidence. | Test adapters/fake resolution only; no live authorized target checked. |
| Website Auditor | Reuses the scanner's verified assets, bounded passive checks, findings, and report history. | Feature tests pass using test adapters; no live target checked. |
| File Check | Private upload, metadata, hash, ownership, retention, and deletion. | Feature tests pass. No malware scan or file safety verdict. |
| Safe Navigation | Administrator-only URL lookups, including local OpenPhish Community Feed matching; submitted destinations are never opened or sent to OpenPhish. | Feed parsing, refresh guards, and matching use fake HTTP responses. OpenPhish is disabled by default pending terms approval; live feed/provider connectivity is unverified. VirusTotal remains optional. |
| Central AI Agent | Owner-scoped threads, bounded evidence context, provider abstraction, opt-in disclosure, rate limits, and explicit failure states. | OpenRouter adapter tested with fakes only. No live request or model is configured. |
| Cybersecurity Academy | Original course material, server-graded quizzes, learner progress/history, administrator publishing. | Feature tests pass. |
| Production error pages | Generic branded 403, 404, 419, 429, 500, and 503 pages avoid exposing exception details. | 404 and 500 behavior tested with debugging disabled. |

## Verified integrations and checks

- Current local environment: PHP 8.2.12 with PDO MySQL, PDO SQLite, cURL, Fileinfo, and OpenSSL available; Composer's non-development platform check passed.
- Local database verification: MariaDB 10.4.32, `zentrix_astra`, all 15 migrations Ran, and required Academy/audit/OpenPhish tables and indexes confirmed. The feature-settings table contains six default-enabled controls; the OpenPhish status table contains its seeded singleton row. Existing application tables were not modified by the additive OpenPhish migration.
- **No live external provider or production host integration is verified.** Etopia's PHP, Laravel, Composer, database, web server, storage, environment-variable, HTTPS, queue, and scheduler capabilities remain unknown.
- Automated queue lifecycle tests run on isolated SQLite; this does not verify a persistent production worker.
- Phase 13 test run: **86 tests passed, 643 assertions**.
- Current verification after the OpenPhish integration: **106 tests passed, 774 assertions**; all 15 migrations report Ran on local MariaDB; production Vite build and targeted Pint checks passed. No live OpenPhish request was made because the feed remains disabled pending terms approval.
- Phase 12 verified fresh SQLite migrations, route references, health readiness, Composer/npm dependency audits, Vite build, and browser spot checks. The later local MariaDB migration repair is recorded in the [Final Test Report](./FINAL_TEST_REPORT.md).
- The full-project Pint check has previously found legacy formatting differences. The Phase 13 touched PHP files pass a targeted Pint check.

## Required external services and deployment dependencies

| Service/capability | Required? | Status |
|---|---|---|
| Verified PHP web host with Laravel 12 support | Yes | Intended host identity/capabilities are unverified; no deployment. |
| MySQL-compatible production database | Yes for the documented production configuration | Not provisioned or tested. |
| Durable private storage for File Check | Yes while File Check is enabled | Current storage is local; host persistence is unverified. |
| Persistent database queue worker | Yes for queued scanner execution | Application job code/tests exist; no production worker is running. |
| Laravel scheduler/cron | Yes for retention tasks | Schedule definitions are confirmed; no production scheduler is running. |
| HTTPS and a canonical domain | Yes for production | Not configured or tested. |
| SMTP or another supported mail delivery provider | Yes for production password-reset delivery | None selected; current default is development log mail. |
| OpenRouter AI | Optional | No live provider requests; requires an owner-selected model and secret configuration. |
| URL-reputation provider | Optional | OpenPhish Community Feed requires no API key and is disabled by default pending terms approval. VirusTotal remains optional; live connectivity is unverified. |

## Security and operational boundaries

- Use the platform web root `public/`; never publish the repository root or `.env`.
- `APP_DEBUG=false` and production secure session cookies are expected. HTTP-to-HTTPS enforcement belongs at the hosting edge unless a host-specific proxy configuration is deliberately reviewed and tested.
- The trusted-host rule is based on `APP_URL`; deploy the intended canonical hostname.
- The File Check disk must be persistent and private. A local or ephemeral disk may lose uploads on release/restart; multi-instance shared storage is not implemented.
- Scanner and auditor checks are limited, passive, authorization-based checks, not penetration testing or comprehensive security certification.
- Safe Navigation and AI have no live provider calls verified. OpenPhish matching uses a local feed and does not send submitted URLs to the feed operator. Review terms before enabling scheduled/manual feed downloads; any credentialed provider such as VirusTotal may receive submitted URLs. AI requires an owner-selected model and a private OpenRouter key.
- The new administrator controls page can pause registration and ordinary-user access to Scanner, Website Auditor, File Check, AI Agent, and Academy. Feature changes do not delete stored records; administrators retain access to paused modules for recovery. `AUTH_ALLOW_REGISTRATION=false` remains a hard restriction that the admin UI cannot override.
- Do not describe File Check as malware detection or scanner/auditor outcomes as proof that a target is secure.

## Launch and maintenance

The operational setup, release sequence, required environment variables, secrets, worker, scheduler, backups, and verification checklist are in [Deployment Guide](./DEPLOYMENT_GUIDE.md) and [Environment Setup](./ENVIRONMENT_SETUP.md). External provider setup is described in the [API Activation Guide](./API_ACTIVATION_GUIDE.md). Current external dependencies and blockers are listed in [Known Limitations](./KNOWN_LIMITATIONS.md) and [Development Status](./DEVELOPMENT_STATUS.md).

Required ongoing operations include database and private-file backups/restores; database queue monitoring; scheduled pruning; dependency updates; HTTPS/domain/certificate renewal; SMTP delivery monitoring; reviewing administrator and security events; and timely PHP/Laravel/Node updates.
