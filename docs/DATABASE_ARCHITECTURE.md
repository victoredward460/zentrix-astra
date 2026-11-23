# ZENTRIX ASTRA Database Architecture

**Status:** MySQL/InnoDB remains the production target. Phases 5-11 add shared assessment data, private file metadata, administrator-owned URL history, AI conversations, Academy learning records, account status, and minimized platform audit events. Historical OpenPhish tables remain in the additive schema for compatibility only; current code does not query or refresh them.

## 1. Storage strategy

- **Persistent deployment database:** MySQL with InnoDB, `utf8mb4`, strict mode, foreign keys, and UTC timestamps.
- **Local/test support:** SQLite may remain useful for isolated tests, but it is not the selected persistent deployment database.
- Define all schema changes with Laravel migrations. Use foreign keys and indexes for ownership, lifecycle queries, and retention. Use JSON only for bounded supplementary evidence/configuration, not as a substitute for relational columns.
- Validate the current migration baseline and MySQL capabilities before introducing feature tables. Avoid altering the existing starter migrations; add forward migrations.
- No production administrator, API key, or private sample data should be seeded as a default.

## 2. Existing starter schema

Existing migrations define:

- `users`, `password_reset_tokens`, and database `sessions`.
- `cache` and `cache_locks`.
- `jobs`, `job_batches`, and `failed_jobs`.

The user model has name, unique email, email verification timestamp, password, remember token, timestamps, a Phase 3 `role` column (indexed, default `user`), and a Phase 11 `is_active` flag (indexed, default `true`). The old starter seeder's hard-coded demo account was removed in Phase 3; no default user or administrator is seeded. All current migrations were applied to the configured local MariaDB; production migration behavior remains unverified.

## 3. Proposed logical entities

The following entities are design-level proposals and should be introduced only by their authorized phase.

### Identity and access

- `users`: retains Laravel's identity fields and includes `role` (`user` or `admin`) and `is_active`. Role/status assignment is not mass assignable; disabling an account rotates its remember token and removes database-backed sessions where available.
- Separate `roles`, `permissions`, and pivots are not currently needed for these two roles; add them only if finer-grained permissions are approved.
- Password reset tokens and database-backed session records use Laravel's existing `password_reset_tokens` and `sessions` tables.
- Use policies for record ownership and action-specific authorization even when role checks exist. Safe Navigation and administration routes enforce administrator access on the server.
- The first administrator is provisioned by the interactive CLI bootstrap command, never by registration or a seeded credential.

### Assets and assessments

- `security_assets` (Phase 5): user owner, normalized hostname, authorization-attestation timestamp, verification status, SHA-256 hash of a random DNS TXT challenge, its expiry, and verified timestamp. Unique `(user_id, hostname)` and `(id, user_id)` keys enforce owner scope. Plain challenge values are not stored in the asset record.
- `scan_runs` (Phases 5-6): owner, registered asset, scan type (`security_scanner` or `website_audit`), `requested`/`queued`/`processing`/`completed`/`failed` status, lifecycle timestamps, and a sanitized failure code. Composite foreign key `(security_asset_id, user_id)` prevents cross-owner asset/run association; `(id, user_id)` supports owner-constrained finding relations.
- `scan_check_results` (Phase 5): run, stable check key, outcome (`passed`, `finding`, `inconclusive`, `error`, or `blocked`), short summary, and bounded JSON evidence. `(scan_run_id, check_key)` is unique.
- `security_findings` (Phases 5-6): owner, scan run, rule key, title/description, recommended corrective action, severity, verification state, open/resolved state, bounded evidence, and detected timestamp. Composite `(scan_run_id, user_id)` foreign key preserves tenant ownership; `(scan_run_id, rule_key)` is unique. Automated scanner/auditor findings are unverified observations.
- The Website Auditor reuses the shipped `scan_runs`, `scan_check_results`, and `security_findings` through `scan_type=website_audit`; it does not duplicate assessment tables. It adds only checks/results whose keys are unique within each run. Do not refactor the shared `scan_runs` model before a concrete use case requires it.

Implemented Phase 5 relations:

```text
User 1--many SecurityAsset
SecurityAsset 1--many ScanRun
User 1--many ScanRun
ScanRun 1--many ScanCheckResult
ScanRun 1--many SecurityFinding
User 1--many SecurityFinding
User 1--many FileCheck
User 1--many SafeNavigationCheck (administrator-only, owner-scoped)
```

Use indexes on owner/asset status, asset/run creation time, run status, and finding state/severity. Avoid a polymorphic finding source until a real use case and its referential-integrity behavior are specified.

### File Check

- `file_checks` (Phase 7): owner, generated private storage key, original display name (treated as untrusted text), byte size, detected MIME type, SHA-256, `processing`/`completed`/`failed` state, sanitized failure code, processed timestamp, and timestamps. The migration indexes owner/creation and status/creation, and restricts user deletion until private objects are removed.
- Phase 7 does not add `file_check_indicators`: no antivirus or reputation source is configured, and no indicator/finding is fabricated. A separate indicator table belongs to a later explicitly approved integration if its provenance and retention are defined.

Keep uploads outside `public/`; database records hide raw storage keys from serialization. Phase 7 accepts valid uploads as opaque bytes up to 10 MiB; it stores only generated `.bin` object names, and never executes, decompresses, parses, renders, or exposes file content. Detected MIME type is descriptive, not an allow/deny or malware verdict. Users may delete immediately; a daily retention command removes both private object and record after 30 days. Optional future reputation results must be attributed to their provider and must not be described as a local confirmed finding.

### Safe Navigation (Phase 8)

- `safe_navigation_checks`: submitting administrator (`user_id`), normalized HTTP/HTTPS URL (including path/query), overall classification, bounded JSON provider results, `assessed_at`, and timestamps. The migration indexes classification and `(user_id, created_at)` and restricts user deletion while rows remain.
- Each record is visible only to the administrator who submitted it. Submission requires an authorization attestation and explicit acknowledgement that the normalized URL may be shared with a selected provider. No URL-target HTTP request, redirect, or automatic open is performed.
- Provider results record source key/name, classification, bounded threat types/evidence, and optional cache expiry. No result or unmatched URL is not proof of safety.
- Daily `safe-navigation:prune` removes history older than 30 days. This retention applies to the submitted URL and provider evidence, which may contain sensitive path/query data.
- Provider credentials/status are deployment configuration, not database rows. VirusTotal remains an optional credentialed provider. OpenPhish has been retired from current runtime behavior. Provider terms and credentials are described in [API requirements](./API_REQUIREMENTS.md#safe-navigation-reputation-providers-phase-8).

### Retired OpenPhish tables

The legacy `openphish_indicators` and `openphish_feed_status` tables are intentionally retained by their original migration to avoid deleting existing database data. Current application code does not refresh, read, or display these tables.

### AI assistant

- `agent_threads`: owner, bounded title, last activity time, and timestamps. A unique `(id, user_id)` key supports the owner-composite message relationship.
- `agent_messages`: thread and owner, `user`/`assistant` role, `completed`/`failed` status, content (nullable for failed responses), provider/model identifiers, sanitized failure code, token counts, and timestamps. A composite `(thread_id, user_id)` foreign key prevents a message from being attached to another user's thread. Thread deletion cascades to its messages.
- A selected finding is read through the authenticated user's `securityFindings` relationship and is used only to build that request's bounded context. No finding evidence, file content, provider payload, API key, or provider error body is duplicated into a separate context table.
- Daily `agent:prune` deletes conversations with no activity in 30 days. Users may delete their own threads immediately. Message text is private account data and is rendered as escaped text.

AI output is explanatory content. It must not be inserted into `security_findings` as confirmed evidence without a deterministic/approved verification workflow.

### Academy

- `academy_categories`: name, unique slug, description, publication state, and order.
- `academy_courses`: category, title, unique slug, summary, beginner/intermediate/advanced level, estimated duration, publication state, and order.
- `academy_lessons`: course, title, course-scoped slug, summary, plain-text educational content, publication state, and order. Lesson content is rendered as escaped text, not interpreted HTML.
- `academy_quizzes`: at most one quiz per lesson, title, pass percentage, and publication state.
- `academy_quiz_questions`: quiz, prompt, four named answer options, correct option, explanation, and order. Correct answers are omitted from learner-page data and checked only on the server.
- `academy_lesson_progress`: user, lesson, most recent view, and optional completion timestamp. A unique `(user_id, academy_lesson_id)` constraint prevents duplicate progress rows.
- `academy_quiz_attempts`: user, quiz, integer score, question count, pass state, submission time, and a snapshot of the submitted answers and feedback. Attempts are accessed only through the user's own relation. Passing a published quiz completes its lesson; lessons without a published quiz can be explicitly marked complete.

Course and lesson content is seeded from original beginner-friendly material by `AcademySeeder`; `DatabaseSeeder` invokes it. `firstOrCreate` preserves administrator edits when the seeder is run again. Administrators can draft, update, and publish categories, courses, lessons, and quizzes; learners can only access published content. Learning data remains separate from security findings and assessment records, and no external content API or credential is required.

### Administration and audit

- `platform_audit_events` (Phase 11): nullable actor, validated event key, optional subject type/id, timestamp, and allowlisted metadata. It records login/logout, inactive-session revocation, role/status changes, Academy content mutations, feature-access changes, and sensitive administrator report/configuration access. It never stores credentials, passwords, tokens, URLs, prompts, file hashes, or user content. The daily `platform-audit:prune` command removes rows older than 365 days.
- `platform_feature_settings` (Phase 11 extension): unique non-secret feature key, enabled state, nullable administrator updater, and timestamps. The migration inserts enabled defaults for public registration and five user-facing modules. An environment-level registration prohibition takes precedence over this table. It contains no secrets, and feature switches never delete existing user data.
- No provider credential or secret is stored in application tables. Safe Navigation stores only the bounded URL assessment record described above; provider credentials remain in `.env` or the deployment secret store.
- Platform settings should remain in deployment configuration when secret or environment-specific; only non-secret user-managed preferences belong in application tables.

## 4. Data integrity, privacy, and lifecycle

- Associate all future user-owned records, especially scans and private file-check records, with a non-null owner foreign key and enforce ownership in policies and queries before exposing routes.
- Prefer restrictive deletion for evidence records until retention behavior is explicit. Use a documented cascade only where data ownership and audit needs permit it.
- Use unique constraints for normalized asset ownership and deterministic finding fingerprints where applicable.
- Store UTC timestamps and index the dashboard's bounded time/status queries.
- Bound evidence text and JSON size; store only evidence needed to explain a result. Never persist full website responses by default.
- Define retention, user deletion, upload cleanup, queue payload, and audit-event policies before production. Prune expired records and private objects through tested jobs/commands.
- Use transactions when creating a run and dispatching related work; queue only after commit to avoid orphan jobs and missing records.
- Keep migrations reversible where feasible and test both fresh migration and deployment upgrade paths.

## 5. Dashboard query sources

Dashboard metrics must be calculated from actual `scan_runs`, `security_findings`, `file_checks`, and any future Academy progress summaries, always filtered by access scope and time range. Distinguish zero activity from an unavailable service or failed job. Avoid a cached number that outlives the records or permissions it summarizes.

Phase 4 introduced the authenticated `DashboardService` and typed snapshot, metric, scan, finding, and activity data structures. Phase 5 integrates actual scan and finding rows; Phase 7 adds the authenticated user's real file-check count and recent file-record timeline events. All counts and recent rows are queried through the authenticated user's relationships. Empty records produce real zero counts and empty states, not fabricated activity. Phase 10 Academy progress is shown in Academy history; Academy metrics have not been added to the Security Dashboard.

## 6. Migration sequencing

Introduce schema in dependency order: identity/access; Phase 5 assets, scan runs, check results, and findings; Phase 6 scan-type/recommendation additions; Phase 7 file checks; Phase 8 Safe Navigation history; Phase 9 AI threads/messages; Phase 10 Academy categories, courses, lessons, quizzes, questions, progress, and attempts; and Phase 11 account status/audit/feature settings. Each phase should include migration tests and representative factories without production secrets or fabricated operational findings.
