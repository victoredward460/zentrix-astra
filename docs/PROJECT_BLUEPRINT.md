# ZENTRIX ASTRA Project Blueprint

**Status:** Architecture baseline, maintained as the platform evolves. The current implementation includes the verified-asset scanner, Website Auditor, private metadata-only File Check, administrator-only Safe Navigation, AI/Academy modules, and the Phase 14 brand and audited platform controls. Safe Navigation adapters exist, but live credentials/connectivity and production deployment are unverified.

## 1. Project purpose and operating rules

ZENTRIX ASTRA is planned as a defensive cybersecurity web application built with Laravel, PHP, MySQL, Blade, Tailwind CSS, and vanilla JavaScript. Users should be able to assess systems and files they own or are explicitly authorized to assess. Findings must be tied to reproducible evidence and clearly labelled as unverified, suspicious, or confirmed. AI may explain recorded evidence; it must not create or promote findings on its own.

Implementation remains phase-based. Build and verify one approved phase at a time. Do not implement exploitation, credential attacks, stealth, malware execution, destructive payloads, or evasion. Do not connect a paid or credentialed external service until its provider, cost, terms, data handling, and credentials have been reviewed with the project owner.

## 2. Initial workspace assessment (Phase 0)

The workspace is a Laravel starter, not yet a feature-bearing ZENTRIX ASTRA application.

- Laravel's framework package is present in `vendor` at **12.72.0**. `composer.json` allows `^12.0`; its PHP constraint is `^8.2`.
- The existing Composer dependencies are Laravel Framework, Laravel Tinker, and the default Laravel development tools (Faker, Pail, Pint, Sail, Mockery, Collision, and PHPUnit). Existing frontend dependencies are Vite, Tailwind CSS 4, the Laravel Vite plugin, Axios, and concurrently.
- The runtime configuration supports SQLite and MySQL; `.env.example` defaults to SQLite. A SQLite database file exists. The checked-in starter migrations define users, sessions, password resets, cache, and queue tables.
- Existing application code is the stock `User` model, provider, base controller, welcome route/view, example tests, and Laravel setup. Preserve the Laravel bootstrap, configuration, migration history, and Vite/Tailwind setup.
- The starter `DatabaseSeeder` inserts a `test@example.com` user. It is development/demo data and must not become a production administrator.
- A local `.env` file exists but was not read; do not put its values in documentation or logs.
- No PHP command execution was possible through the available command runner in this inspection. The exact PHP CLI version, loaded extensions, Artisan boot, Composer CLI health, database connection, and migration state are therefore **unverified**. The project requires PHP 8.2 or newer and the extensions listed in [Development roadmap](./DEVELOPMENT_ROADMAP.md#environment-and-readiness).
- No generated frontend build output was found. JavaScript package installation/build readiness was not tested.

The default README and welcome page are Laravel branding/starter content. There are no implemented ZENTRIX ASTRA modules, custom authentication screens, roles, permissions, scan workflows, or product data yet.

## 3. Application structure

Use the existing Laravel layout and add responsibilities only as their phase is approved:

```text
app/
  Console/Commands/   Restricted operational commands, including first-admin bootstrap
  Data/               Typed dashboard snapshots, metrics, and display records
  Enums/              Stable user-role values
  Http/
    Controllers/       Thin web controllers
    Middleware/        Cross-cutting route guards where policies are insufficient
    Requests/          Input validation and authorization
  Jobs/                Long-running, bounded assessment work
  Models/              Eloquent records and relationships
  Policies/            Resource ownership and action authorization
  Providers/           Laravel service bindings and bootstrapping
  Support/             Small cross-feature rules such as password policy
  Services/
    Assessments/        Safe scan and website-audit orchestration
    Scanner/            DNS ownership, public-address policy, and bounded passive checks
    FileCheck/          Private file-check workflow
    AI/                Provider-neutral, evidence-grounded assistant
    Security/           Shared validation, authorization, and audit helpers
    DashboardService.php Account-scoped dashboard aggregation
database/
  migrations/
  seeders/
resources/
  views/
    layouts/            Shared Blade shell and navigation
    components/         Reusable Blade UI
    dashboard/
    file-check/
    scanner/
    website-auditor/
    academy/
    admin/
  css/                  Tailwind entry point and product styles
  js/                   Small vanilla-JavaScript modules
routes/
  web.php               Same-origin, session-authenticated Blade routes
tests/
  Feature/              Routes, authorization, forms, and persistence
  Unit/                 Service rules and edge cases
```

Keep controllers focused on HTTP concerns. Put bounded assessment work in Laravel jobs and domain workflows in services. Avoid introducing a public API, package, or abstraction until a concrete phase needs it.

## 4. Modules and relationships

| Module | Responsibility | Relationship to shared platform |
|---|---|---|
| Security Scanner | Safe checks against explicitly authorized, registered assets | Creates assessment runs and evidence-backed findings |
| Website Auditor | Passive website configuration/content checks on explicitly authorized domains | Creates assessment runs and findings; uses guarded outbound HTTP |
| File Check | Store private upload metadata and SHA-256 without executing, parsing, or publishing file content | Owner-scoped records with explicit processing states and 30-day retention; no malware verdict or external reputation lookup |
| Central AI Agent | Explain selected, account-owned findings with clearly labelled AI-generated responses | Sends bounded, owner-authorized context to the configured OpenRouter model/provider under explicit data and cost constraints; never originates findings |
| Cybersecurity Academy | Published learning paths, original lessons, quizzes, and learning history | Learner progress and quiz attempts are private to the signed-in user; administrators manage and publish content |
| Security Dashboard | Show real scans, findings, file checks, and learning progress | Read-only, account-scoped aggregation over persisted application records; Phase 4 adds a typed snapshot contract and explicit unavailable/empty states |
| Safe Navigation | Administrator-only URL normalization, reputation-result history, provider status, and evidence-based warnings | No arbitrary URL fetching or automatic redirects; provider clients are gated pending terms, data-sharing, and credential approval |
| Platform Administration | Manage users, roles, configuration, and audit events | Restricted to explicitly authorized administrators |

The shared assessment lifecycle and normalized finding model keep scanner and website-audit results available to the dashboard and AI agent. The Website Auditor uses the shared `scan_runs` / `scan_check_results` / `security_findings` tables, distinguished by scan type, and stores an explicit corrective recommendation for each finding. It performs one bounded HTTPS HTML retrieval for local title/meta/mixed-content inspection and never follows redirects or fetches embedded resources. Phase 7 stores files privately under generated keys and reports only the untrusted display name, server-detected MIME type, size, SHA-256, and processing state. Files are not parsed, executed, downloaded, or sent to external providers; metadata/hash capture is not malware detection. Phase 10's Academy is a separate learning domain with original seeded content and per-user progress; it does not manufacture security-assessment records or statistics.

Phase 5 adds `security_assets`, `scan_runs`, `scan_check_results`, and `security_findings`. The dashboard calculates actual scanner counts/history/findings and Phase 7 file-record counts/activity through authenticated owner relationships. File-check history and detail remain in the File Check module. Academy progress and quiz history are displayed in their own account-scoped learning views; they are not security metrics. Zero counts represent zero matching records, not a clean bill of health.

Phase 8 adds `safe_navigation_checks`, an administrator-owned history table for normalized URLs, the classification, bounded per-provider results, and assessment time. It is separate from target scanning: the application never requests a submitted destination, follows its redirects, or opens it. VirusTotal is the only active optional credentialed reputation lookup, and the URL is shared only after explicit disclosure acknowledgement. The former OpenPhish runtime integration has been retired; its historical tables are retained without reads or refreshes.

## 5. Authentication and access control

- Use Laravel's session guard and Eloquent user provider. The Phase 3 implementation includes server-side password hashing, CSRF-protected forms, session regeneration/invalidation, login and recovery throttles, and the native password broker.
- The initial roles are `user` and `admin`, stored in a non-mass-assignable `users.role` field. Phase 11 adds a non-mass-assignable `users.is_active` flag. Disabled accounts cannot sign in; active sessions are revoked on the next request, remembered-login tokens are rotated, and database sessions are removed where supported. Expand to normalized roles/permissions only if a concrete authorization requirement needs them.
- Use Laravel Gates and Policies for platform actions and record ownership. Deny by default. Phase 3 protects profiles and administration routes; Phase 5 adds owner policies for registered assets and scan runs; Phase 7 restricts file record views/deletion to their owner.
- Public registration creates ordinary users only and can be disabled with `AUTH_ALLOW_REGISTRATION=false`. Never accept a role or admin flag from registration input. The first administrator is created only through the interactive `zentrix:bootstrap-admin` CLI command, which has no default credentials and refuses to run once an admin exists.
- Treat authorization as both role permission and record ownership. Users may only view or start work on assets they own or have been authorized to assess.
- Safe Navigation and platform-administration routes require both authentication and a backend administrator-role check on every request. Safe Navigation records are scoped to the submitting administrator; hiding a link is not access control. Platform Controls are backed by audited server-side settings and middleware, not just navigation visibility.
- Phase 11's platform admin routes provide live database totals, user/role/status management, owner-linked assessment reports, Academy progress summaries, audit history, and read-only integration status. Never expose AI conversations, file contents, or provider credentials to this admin surface. Content and sensitive report access events are audited with allowlisted metadata only.
- Do not add a third-party authentication/permissions package without demonstrating a need and checking compatibility, license, maintenance, and cost.

## 6. Shared services

Build only as needed, following these service boundaries:

- **Asset authorization and scope:** canonicalize registered targets, record who authorized them and for what scope, and reject out-of-scope targets.
- **Assessment orchestration:** create auditable run records, queue bounded work, track state, and surface failures.
- **Finding normalization:** validate severity, confidence, verification state, evidence, and remediation; preserve source and timestamps.
- **Outbound HTTP policy:** allow only approved public targets; prevent SSRF, unsafe redirects, DNS-rebinding bypasses, internal-network access, metadata endpoints, and unbounded responses.
- **Private file workflow:** enforce the 10 MiB upload cap and hourly rate limit, store outside public web roots, compute hashes, and never execute, parse, decompress, render, or serve uploaded content.
- **AI boundary:** expose only explicitly selected, owner-authorized, minimized records through a provider interface; validate bounded plain-text output and display provenance and uncertainty. No tools, browsing, file contents, or finding mutation.
- **Audit logging:** record security-relevant administrative and assessment events without secrets, passwords, raw credentials, or unnecessary sensitive payloads.

## 7. Security architecture

- Keep all state-changing browser requests behind Laravel CSRF protections, validation, authorization, rate limits, and safe error responses.
- Use Eloquent/query-builder bindings; never interpolate user input into SQL.
- Escape Blade output by default; sanitize only where rendering rich text is explicitly required.
- Store secrets only in environment/deployment secret stores. Keep `.env` out of source control; document placeholders only in `.env.example`.
- Restrict assessment targets to explicitly authorized assets. Apply per-user and per-target limits, bounded timeouts, response-size limits, redirect revalidation, and private/reserved IP blocks at resolution and connection time.
- The Phase 5 scanner accepts hostnames only, requires an authorization attestation and DNS TXT ownership challenge, rejects mixed public/private DNS answers, pins cURL requests to a validated address, confirms the peer address, disables automatic redirects and proxies, and uses HTTP HEAD on ports 80/443 with verified TLS and finite timeouts.
- Safe Navigation accepts normalized HTTP/HTTPS URLs only, rejects embedded credentials and local/private/reserved or ambiguous numeric hosts, and performs no outbound request to the submitted URL. Configured credentialed providers receive submitted URLs only after disclosure. Results distinguish malicious, suspicious, no threat detected by available sources, unable to verify, and integration unavailable. An absent, unavailable, or unmatched result is never described as proof that a URL is safe.
- Keep uploaded files on a dedicated private disk, use generated storage names, enforce the 10 MiB upload cap and hourly rate limit, and avoid returning raw paths. Never execute, decompress, parse, render, or serve an uploaded file.
- For the AI Agent, require per-message disclosure, cap context/history/output, restrict provider/model routing and price, and keep prompts out of application logs. Strict ZDR is a configurable requirement; if disabled, disclose the changed retention risk. Disable tools and treat conversation and finding text as untrusted; responses are explanatory, never deterministic findings.
- Distinguish tool output from verified facts. A transport/API failure is an error or unavailable status, never a successful or clean result.
- Minimize retention of target responses, uploaded files, and AI prompts; define retention and deletion rules before production.
- Use least privilege for application/database accounts and separate local, test, and production credentials.

## 8. External integrations

No external provider is required for local scanner, auditor, or file metadata workflows. VirusTotal remains an optional credentialed provider subject to its terms. Phase 9's OpenRouter adapter uses NVIDIA Nemotron 3 Ultra free only, with NVIDIA-only routing, no provider fallbacks, and zero price caps. Following owner authorization, strict ZDR is disabled by default and provider data collection defaults to `allow`; users must be warned that NVIDIA may retain and use prompts under its policy. File Check sends a message only after explicit consent, and file-record explanations send only minimized metadata and aggregate counts, never file bytes or identifiers. Review the [AI and reputation API requirements](./API_REQUIREMENTS.md) before enabling an integration.

Before any integration is selected, verify the provider's official documentation, current pricing/free tier, terms, data retention and processing location, rate limits, and security model. Inform the project owner and obtain approval before adding a paid dependency or sending user/target/file data externally. Never request a secret through ordinary chat. See [API requirements](./API_REQUIREMENTS.md).

## 9. Database, tests, and deployment

Use MySQL/InnoDB and `utf8mb4` for persistent deployment data. Keep SQLite available for fast isolated tests. Add schema through forward-only Laravel migrations and test migrations from a clean database. The logical entities and indexes are defined in [Database architecture](./DATABASE_ARCHITECTURE.md).

For each phase, add focused unit tests for service rules and feature tests for routes, authorization, validation, database persistence, and rendered behavior. Use `RefreshDatabase`, factories, fake queues/filesystems/HTTP providers, and seeded deterministic fixtures. Test denied access and failure paths as well as success paths. Do not represent a test as passing unless it was run.

Deploy behind HTTPS with `APP_DEBUG=false`, generated `APP_KEY`, secure cookies, a least-privilege MySQL account, queue workers supervised and restarted on deployment, scheduled pruning, backups, log monitoring, and environment-specific secrets. Keep web-serving permissions restricted to `public/`; configure production workers and outbound network egress with explicit limits. Rehearse restore and queue-failure procedures before production.

## 10. Phase boundary

Implementation remains phase-gated. Phase 5 uses Laravel's database queue without Redis and requires no external API key. Phase 7 File Check is synchronous and uses PHP Fileinfo, private local storage, and the Laravel scheduler for retention; it requires no added package or external API key. Phase 8's local workflow and SQLite migration need no key, but provider-backed reputation results remain blocked on the owner's service/commercial-use decision and local secret configuration. Phase 9's OpenRouter adapter has passed harmless live tests with free Nemotron; local training-data permission is explicitly enabled, and strict ZDR is unavailable for this endpoint. Phase 10 Academy uses local database content and requires no external service or credential for core learning features; optional AI drafting uses the Phase 9 integration. MySQL deployment behavior remains unverified.

## Official Laravel references

- [Laravel 12 Authentication](https://laravel.com/docs/12.x/authentication)
- [Laravel 12 Authorization](https://laravel.com/docs/12.x/authorization)
- [Laravel 12 Queues](https://laravel.com/docs/12.x/queues)
- [Laravel 12 File Storage](https://laravel.com/docs/12.x/filesystem)
