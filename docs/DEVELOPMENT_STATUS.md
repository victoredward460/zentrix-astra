# ZENTRIX ASTRA — Current Development Status

## Summary

The local Laravel application has its core defensive-security modules, account-scoped authentication, Academy, AI foundation, optional VirusTotal lookup, and an existing administrator area. OpenPhish runtime integration is retired while its historical migration/tables remain. Public signup creates ordinary users only; administrator routes remain role-protected.

The project is **not production-ready**. No production host has been identified and validated, and provider credentials/connectivity, deployment services, and production operations remain unverified.

## Verified locally

| Area | Current status |
|---|---|
| Database | Configured local XAMPP MariaDB reports **16/16 migrations Ran**. Read-only schema inspection verified the Academy composite index `ac_courses_cat_pub_sort_idx`, application tables, and the additive VirusTotal File Check columns. Production DB/backup/restore behavior is unverified. |
| Tests | Full Laravel suite: **119 passed, 817 assertions**. |
| Frontend | `npm run build` passed with local Oxanium and updated application assets. SVG and ICO favicon variants are updated. |
| PHP formatting | Targeted Pint check passed for all changed PHP files. |
| Browser | Public landing page was checked at 390, 768, and 1366 px. The redesigned AI Agent was checked at 375 and 1366 px with no horizontal overflow, and a new conversation persisted after reload. Authenticated admin multi-viewport checks remain outstanding. |
| Provider behavior | OpenRouter allows users to choose between free Nemotron 3 Ultra (default) and free Nemotron 3.5 Super VL. Live endpoint discovery and harmless chat completions succeeded for both; routing remains NVIDIA-only with provider fallbacks disabled and a zero-price cap. Strict ZDR is disabled locally. Provider data collection is explicitly allowed locally for training; the environment example and config default remain `deny`. |

## Administrator controls

The Platform Controls page is part of the existing administrator system. It can pause/re-enable public registration and ordinary-user access to Security Scanner, Website Auditor, File Check, AI Agent, and Academy. Server-side middleware enforces module switches; a paused module returns an explicit unavailable response. Administrators retain access to paused modules so they can restore service. Historical records are preserved.

`AUTH_ALLOW_REGISTRATION=false` is a hard environment-level restriction and cannot be overridden through the admin page. Every feature change is recorded in the platform audit log.

## External integrations

- **AI Agent:** The shared adapter defaults to `nvidia/nemotron-3-ultra-550b-a55b:free`; users can select either that model or `nvidia/nemotron-3.5-super-vl:free` in the chat composer. AI sharing requires one saved, revocable account-level consent rather than a checkbox on every prompt. Both models remain NVIDIA-only, free-only, no-fallback, and zero-price. Live provider metadata discovery and harmless chat completions succeeded for both model selections; feature tests cover model switching and conversation continuity across chat messages.
- **Academy materials:** Administrators can generate validated HTML and PDF learning guides. Files use private local storage, owner-only download routes, persistent signed share links, and deletion-based link revocation. Focused tests cover escaping, generated file formats, access control, and shared downloads.
- **VirusTotal:** API v3 URL reputation and SHA-256 hash lookups are implemented and live-tested. File Check sends only the digest and never uploads file content. Confirm the product's licensing eligibility before commercial use.
- **Email:** Local password reset uses Laravel's log mailer. Production email delivery needs a real mail provider.
- **Provider keys:** Keep keys in ignored local `.env` or the deployment secret manager. The status UI shows credential presence, not successful connectivity. See [API Activation Guide](./API_ACTIVATION_GUIDE.md).

## Before launch

1. Identify the exact production host and verify Laravel/PHP, Composer, database, web root, secrets, storage, queue, and scheduler support.
2. Provision and migrate the production database; verify persistent private file storage and tested backups/restores.
3. Configure HTTPS, production session/security settings, SMTP delivery, a supervised queue worker, and the Laravel scheduler.
4. Choose external providers only after confirming commercial eligibility, data-sharing, pricing, and retention.
5. Bootstrap an administrator securely, test access and recovery, and perform authenticated responsive/browser checks on the actual deployment.

See [Final Project Documentation](./FINAL_PROJECT_DOCUMENTATION.md), [Known Limitations](./KNOWN_LIMITATIONS.md), [Environment Setup](./ENVIRONMENT_SETUP.md), and [Deployment Guide](./DEPLOYMENT_GUIDE.md) for detailed requirements.
