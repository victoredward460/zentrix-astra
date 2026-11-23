# ZENTRIX ASTRA Development Roadmap

**Status:** Phases 0-11 are implemented with explicit owner authorization. Phase 12 local verification is complete and documented in [the final test report](./FINAL_TEST_REPORT.md). Phase 13 deployment preparation is documented, but no deployment occurred and the project is not launch-ready: “Etopia Deploy” is not identified by official hosting documentation, MySQL connectivity/migration behavior, persistent storage, queue/scheduler operations, SMTP, HTTPS, backups, and broader authenticated responsive-browser checks remain unverified. Safe Navigation and AI have no live provider requests.

## Phase sequence

| Phase | Proposed title | Scope and exit evidence | Prerequisite |
|---|---|---|---|
| 0 | Project Inspection and Architecture | Inspect the starter, record environment limits, and create the blueprint, roadmap, database, API, and development-log documents. No application modules. | Complete after documentation review |
| 1 | Core Application Foundation | Confirm PHP/Composer/extensions, configure MySQL without exposing secrets, establish migrations/test/build baseline, and apply safe application configuration and health checks while preserving starter code. | Phase 0 approval and runtime readiness |
| 2 | Design System and Application Interface | Build the public landing page, brand/visual tokens, shared Blade components, responsive workspace shell, navigation, and explicit empty/loading/error/notification states. Keep unimplemented modules disabled and clearly labelled. | Phase 1 |
| 3 | Authentication, User Management and Access Control | Implement session authentication, password reset, profiles, user/admin roles, owner policies, administrator user management, and an interactive CLI bootstrap. Safe Navigation is protected by backend admin middleware. | Phases 1 and 2 |
| 4 | Security Dashboard | Replace the preview with a responsive, authenticated dashboard; use real account-scoped records when available, typed integration data structures, explicit unknown/empty states, and no fabricated metrics. | Phase 3; feature records as their modules become available |
| 5 | Core Cybersecurity Scanner Engine | Register and DNS-verify authorized hostnames, queue passive HTTP/TLS/DNS checks, persist lifecycle/results/findings, enforce owner access, and use SSRF-resistant address pinning and timeouts. | Phase 3 and explicit assessment scope |
| 6 | Website Auditor | Add broader passive website checks for registered authorized websites with the scanner's SSRF-safe outbound HTTP and redirect/DNS/IP/size/time boundaries. | Phase 5 shared scanner and assessment model |
| 7 | File Check | Add private upload/check workflow, validation, hashes/metadata, result states, retention/deletion policy, and tests; never execute files. | Phase 3 |
| 8 | Safe Navigation | Add administrator-only URL submission, normalization, evidence/status/history, and conservative classifications. Never fetch/open a submitted URL. VirusTotal is the only active optional credentialed reputation provider and requires explicit approval. | Phase 3; provider approval for external lookups |
| 9 | Central AI Agent | Add owner-scoped conversation history, explicit provider disclosure, bounded selected-finding context, a safe OpenRouter adapter, and failure states. No live request until the owner chooses a model/budget and configures secrets. | Phase 5 findings; owner model/privacy/budget decision |
| 10 | Cybersecurity Academy | Build categories, original courses/lessons, server-graded quizzes, owner-scoped learning history/progress, and administrator content publishing. Verified with feature tests. | Phase 3 |
| 11 | Platform Administration | **Complete:** Admin dashboard with real database totals, account/role controls, owner-linked scan/File Check reports, Academy progress, activity/security events, redacted configuration status, and audited user-facing module/registration controls. | Phase 3 |
| 12 | Integrated QA and Release Readiness | **Bounded local verification complete; not release approval.** Automated tests, SQLite migrations, dependency audits, frontend build, routes, readiness, responsive spot checks, and a read-only security review are documented in `docs/FINAL_TEST_REPORT.md`. MySQL, broad authenticated browser coverage, external providers, and production operations remain unverified. | Approved module phases complete |
| 13 | Production Deployment and Final Launch | **Preparation complete; deployment blocked.** Deployment/environment/final-project/limitations documentation and safe production defaults are in place. The exact Etopia Deploy product and its official runtime, database, storage, HTTPS, worker, and scheduler capabilities are not verified; no deployment or live launch check has occurred. | Exact host/plan and operational requirements verified; owner authorization for deployment |

The user explicitly prioritized Safe Navigation as Phase 8; the prior roadmap's Phase 8 AI Agent is moved to Phase 9. Phase 10 Academy and Phase 11 administration are complete. Phase 12's bounded local verification is complete; production readiness remains unverified as noted in its report. Phase 4 consumes scanner records using owner-scoped queries. Phases 5 and 6 share the assessment/finding foundation. Phase 7's uploaded-file workflow stores private metadata/hash and does not scan file bytes for malware. OpenPhish runtime behavior has since been retired while its legacy tables remain for compatibility.

### Phase 5 implementation notes

- Database queue is the default; no Redis service or package is required. Run a worker with `php artisan queue:work --tries=1 --timeout=45` (or use the existing `composer dev` queue listener).
- The scanner accepts hostnames only. Scans require an authorization attestation and a verified DNS TXT challenge; all resolved addresses must pass the public-address policy before requests.
- The cURL probe uses HTTP HEAD on ports 80/443, DNS-pinned connections, strict TLS verification, finite connect/request timeouts, no proxy, bounded response headers, and no automatic redirect handling.
- The feature tests use synthetic DNS and HTTP adapters. They verify persistence and lifecycle without contacting arbitrary public targets.

### Phase 6 implementation notes

- Website audits reuse registered DNS-verified assets and the Phase 5 queue, scan runs, result/finding persistence, address policy, cURL pinning, and access controls.
- The dedicated HTML GET is HTTPS-only, accepts only HTML/XHTML, has an 8-second request timeout and a 256 KiB body cap. Redirects are not followed and embedded resources are not requested.
- Mixed-content results are based on locally parsed HTML/CSS resource references. Cookie findings are based only on sampled Set-Cookie attributes; raw cookie values are not persisted.
- Each auditor finding includes severity, evidence, an unverified state, and a corrective recommendation. Printable reports use the browser print dialog; aggregate risk labels are explicitly limited to observed checks.
- Tests use synthetic resolvers/probes and do not contact external websites. No external API or credentials are required.

### Phase 7 implementation notes

- File Check accepts valid uploaded files as opaque bytes, capped at 10 MiB per file and 10 submissions per user/IP per hour. PHP Fileinfo supplies a detected MIME type; no type is executed, decompressed, parsed, rendered, or publicly served.
- Uploads use a dedicated non-public local disk under `storage/app/private/file-checks`, generated `.bin` object names, owner-scoped records, and owner-only view/delete policies. Results contain the original display name, size, detected MIME type, SHA-256, and processing state.
- Completed means metadata/hash capture succeeded only. It is not a malware verdict. No antivirus engine, reputation provider, external API, or API credential is configured or required.
- Users can delete immediately. The daily `file-checks:prune` schedule deletes objects and rows older than 30 days; production must run Laravel's scheduler (for example, `schedule:run` every minute). A storage deletion error is logged and leaves the record for retry.
- The workflow is synchronous because it only handles bounded metadata and hashing for a 10 MiB maximum. No queue worker or Redis is required. Tests use synthetic uploads and fake private storage.

### Phase 8 implementation notes

- Safe Navigation is protected by authenticated administrator middleware on every route; each record is scoped to its submitting administrator.
- URL submission accepts normalized HTTP/HTTPS targets, requires authorization and provider-disclosure acknowledgements, rejects embedded credentials and local/private/reserved destinations, and is rate limited. It never requests the destination URL, follows redirects, or opens a submitted URL.
- OpenPhish is no longer queried or refreshed by current application workflows; its old tables are retained and untouched. VirusTotal is optional. No reputation provider result is a safety guarantee.
- Optional VirusTotal requirements are documented in [API requirements](./API_REQUIREMENTS.md#safe-navigation-reputation-providers-phase-8). File Check AI processing requires explicit consent and excludes file bytes, filenames, hashes, and storage identifiers.

### Phase 9 implementation notes

- The authenticated Central AI Agent stores owner-scoped threads/messages, supports immediate deletion, and prunes conversations after 30 days. Each request may include only one finding selected from the current user's own records; the prompt uses a bounded allowlist of evidence fields, never uploaded file contents or unrelated records.
- OpenRouter uses the fixed Chat Completions endpoint with configurable connection/request timeouts, no redirects, no streaming, no tools/plugins, a 700-token default output cap, and a per-user limit of 6 messages/minute and 30/hour. Academy generation uses a 2,500-token request budget with reasoning disabled. Requests are restricted to NVIDIA, set `allow_fallbacks: false`, and enforce zero prices. `OPENROUTER_DATA_COLLECTION` defaults to `deny` but is explicitly `allow` locally for provider training; `OPENROUTER_REQUIRE_ZDR` defaults to true but is temporarily false locally because the NVIDIA free endpoint is not ZDR eligible.
- `OPENROUTER_MODEL` defaults to `nvidia/nemotron-3-ultra-550b-a55b:free`; users may select it or `nvidia/nemotron-3.5-super-vl:free` in the AI chat. The app does not select paid models or other providers. Completion POSTs are not retried.
- Current model/price/free-tier/privacy research and the exact credential setup are in [API requirements](./API_REQUIREMENTS.md#central-ai-agent-phase-9). No Composer/npm dependency was added.

### Phase 10 implementation notes

- The authenticated Academy presents published categories, courses, and lessons. Original starter content is added by `DatabaseSeeder`; rerunning it does not overwrite administrator edits.
- Quiz choices are rendered without answer keys. The backend verifies the exact question set, validates each selected option, calculates the score, records a user-scoped attempt snapshot, and marks lesson progress complete only on a passing result. Lessons without a published quiz can be marked complete.
- Administrators manage categories, courses, lessons, quiz thresholds, and questions under backend `role:admin` middleware. Unpublished material is hidden from learner routes. Lesson content is plain text and safely escaped.
- No external API, credential, or dependency is required. Focused feature tests exercise publication, lesson access, answer validation, passing/failing results, progress, attempt ownership, and admin access.

### Phase 11 implementation notes

- Every authenticated route now checks account activity after authentication. Disabled accounts cannot log in; an already-authenticated disabled account is logged out on its next request. Disabling an account rotates its remember token and removes its database-backed sessions. Administrators cannot change their own role/status, and safeguards prevent removing the last active administrator.
- The administrator dashboard uses live counts from users, scans, findings, file checks, Safe Navigation checks, AI thread counts, and completed Academy lessons. Admin scan and File Check reports are linked to owners; AI conversation content and uploaded file contents are not exposed. Academy progress and quiz result history show outcomes only, not submitted answer details.
- `platform_audit_events` stores only validated event keys, subject IDs, and a short allowlist of role/status/content-type metadata. It audits login/logout, status/session events, role changes, Academy content changes, and sensitive admin report/configuration views. URLs, prompts, credentials, file hashes, and user content are excluded. The daily `platform-audit:prune` task retains records for at most 365 days.
- The configuration page reports non-secret runtime values and only credential presence for configured integrations; it never exposes key values or represents configured credentials as verified connectivity. No Safe Navigation or AI provider call is made.
- The dedicated Platform Controls page lets administrators pause/re-enable public registration and ordinary-user access to Scanner, Website Auditor, File Check, AI Agent, and Academy. Backend middleware enforces module switches; administrators retain access for recovery. Registration can be enabled in the admin UI only if `AUTH_ALLOW_REGISTRATION` permits it. Changes are audited without storing secret or user-content values.
- Admin routes remain protected by backend admin middleware and all ordinary user scanner, file, AI, and Academy routes remain owner-scoped. No new dependency or API credential is required.

## Architecture milestones

1. Establish identity, ownership, authorization, auditability, and the shared visual shell before adding sensitive workflows.
2. Implement each module against persistent records, with meaningful empty and error states. The dashboard consumes module data; it does not invent it.
3. Keep scanner and auditor jobs bounded, explicitly authorized, and observable. Keep uploaded files private and inert.
4. Make AI and other external integrations opt-in, provider-neutral, reviewable, and explicit about unavailable/failed states.
5. Integrate and harden only after the component phases pass their focused tests.

## Environment and readiness

### Observed

- Installed framework package metadata identifies Laravel **12.72.0**.
- The project constraint is PHP `^8.2`; the lockfile/dependencies target PHP 8.2 or later.
- `vendor/` and Composer's installed-package metadata are present. The Composer executable itself could not be invoked.
- `composer.json` and `composer.lock` exist. Frontend manifests and source exist. No built public assets were found.
- Database configuration supports MySQL and SQLite. `.env.example` defaults to SQLite; a SQLite file is present. The actual local `.env` was not read.

### Current operational limits

PHP 8.2.12 and Laravel 12.72.0 are available; PHP Fileinfo is loaded. Phase 7's full suite, fresh in-memory SQLite migrations, PHP formatting, Vite build, File Check routes, and retention schedule were verified. Phase 8 verification details are recorded in the development log. No MySQL service is listening on the configured local endpoint, so MySQL connectivity and a live MySQL migration run remain unverified. No live target or reputation provider has been contacted.

### Minimum checks before Phase 1 implementation

Run locally in the project directory (do not paste secrets into chat):

```text
php -v
php -m
composer --version
composer check-platform-reqs
php artisan --version
mysql --version
```

Confirm PHP is 8.2 or newer and the Laravel-required extensions are loaded: `ctype`, `filter`, `hash`, `mbstring`, `openssl`, `session`, and `tokenizer`. Database support also requires `PDO` and `pdo_mysql` for the required MySQL setup. File Check should verify `fileinfo`. PHPUnit may require the XML/DOM extensions expected by its installed version. `intl` is optional unless a selected feature requires it. Use `composer check-platform-reqs` to identify Composer package requirements on the actual runtime.

Then configure a local MySQL database and credentials in the ignored `.env` file, set a private `APP_KEY`, and verify the connection/migrations using the project's configured test/development commands. Never copy those credential values into chat or source control. Resolve any incompatible or missing requirement before beginning feature work; do not install dependencies unless an approved phase needs them.

## Phase completion gate

For each authorized phase: inspect affected files, make a bounded implementation plan, implement only its scope, run targeted tests/build checks, fix failures, update documentation and `docs/DEVELOPMENT_LOG.md`, report changed files/database/dependencies/tests/known issues/outstanding work/credential needs, and stop for approval. A failing or unrun required test means the phase is not confirmed complete.
