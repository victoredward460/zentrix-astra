# ZENTRIX ASTRA — Known Limitations and Launch Blockers

## Launch status

**Not ready for launch; no deployment has been performed.** The name “Etopia Deploy” does not uniquely identify a hosting product with official technical documentation. Do not infer platform support from similarly named services. Provide the exact product/documentation URL and plan name before platform-specific configuration or a deployment attempt.

The official [Etopia website](https://etopia.eco/) and [technical downloads](https://etopia.eco/technical-downloads/) reviewed for this phase describe building-system resources, not PHP application hosting. The following host capabilities are therefore **unverified**, not declared unsupported:

- PHP version and required extensions.
- Laravel 12 compatibility.
- MySQL availability/version/TLS.
- Composer install/build support.
- Web server rewrite rules and `public/` document root.
- Persistent writable storage.
- Secret/environment-variable management.
- HTTPS certificates and redirects.
- Persistent queue-worker process support.
- Cron or Laravel scheduler support.

## Required before launch

1. Identify the exact Etopia Deploy product and plan using its official URL; verify every capability in the [Deployment Guide](./DEPLOYMENT_GUIDE.md).
2. Provision and test the production database. The configured local XAMPP MariaDB has all 15 project migrations applied, and read-only schema inspection confirmed the application tables and key constraints. Production database version/TLS, production migration behavior, backups, and restore remain unverified.
3. Confirm persistent private storage for `storage/app/private/file-checks`. If storage is ephemeral or the application runs on multiple instances, File Check persistence is not production-ready without shared durable storage or a deliberately implemented and tested private object-storage adapter.
4. Run and monitor a database queue worker for scanner jobs, and run Laravel's scheduler every minute for pruning tasks.
5. Configure a real SMTP/mail service. The current `log` mailer is development-only and logs reset links instead of delivering them.
6. Configure a domain, HTTPS, HTTP-to-HTTPS redirection, host/proxy settings, and production secret variables. Verify secure cookies and the canonical `APP_URL`.
7. Build and deploy `public/build`; it is excluded from Git. Confirm error logging and health checks from the actual host.
8. Create the first administrator via the interactive CLI and perform authenticated user/admin smoke tests. No default administrator credentials are seeded.
9. Verify an encrypted database and private-file backup can be restored.

## Integrations and product limitations

- **AI:** OpenRouter key authentication is rejected with HTTP 401 even though the value has an `sk-or-v1-` format and the model is validly formatted. The key may be revoked or belong to the wrong workspace. No chat completion was sent and no inference credits were consumed. Reissue the key locally, clear Laravel config, and retest.
- **Safe Navigation and Website Scanner:** VirusTotal API v3 is integrated for URL reputation and SHA-256 hash lookups; provider availability and terms still apply. OpenPhish has been retired. No match or clean-looking totals are not safety guarantees. File Check's AI explanation is advisory and never scans the uploaded file contents.
- **Administrator controls:** User-role/status changes and audited switches for public registration and ordinary-user module access are implemented. Module controls pause access without deleting previous records or shutting down the entire app; administrators retain access to paused modules. Secret values, database reset/restore, arbitrary environment edits, and destructive data administration are deliberately not exposed through the UI.
- **Email:** Password reset tests use Laravel fakes/log mail; no production SMTP service is verified.
- **Live email:** The effective mailer is `log`; no real message was sent. Choose a mail provider, configure its credentials privately, and provide a test inbox you control before testing delivery.
- **File Check:** Captures private file metadata and SHA-256 only. It does not scan for malware or declare files safe.
- **Scanner and Website Auditor:** Only controlled, passive defensive checks on user-authorized DNS-verified assets are intended. Tests use synthetic resolvers/probes; no public target was contacted during verification.
- **Production queue/storage topology:** The database queue and local private file disk work within the application design, but host process supervision, ephemeral disk behavior, shared storage, scaling, and operational recovery have not been exercised.
- **Browser coverage:** Responsive spot checks cover the public landing and sign-in pages and the redesigned AI Agent at desktop and mobile widths. Authenticated visual checks of all modules and platform controls across desktop and mobile remain outstanding.
- **Security review:** The bounded code review and passing tests are not a penetration test, independent security audit, or guarantee that the platform is free of vulnerabilities.

## Support and maintenance

- Keep PHP, Laravel dependencies, Node build tooling, and operating-system packages patched.
- Review dependency advisories and rerun tests/build on each release.
- Monitor logs, readiness, queue depth/failed jobs, disk capacity, database health, TLS expiry, mail delivery, and scheduled commands.
- Maintain encrypted off-site database and private-file backups; test restore regularly.
- Review and retain administrator/security audit events according to the configured retention policy.
- Reassess AI/reputation provider terms, privacy, pricing, and credentials before enabling any external data flow.
