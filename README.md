# ZENTRIX ASTRA

An evidence-led defensive-security platform built with Laravel 12.

## Status

The application has not been deployed and is **not ready for launch**. The intended hosting product “Etopia Deploy” could not be identified unambiguously from official hosting documentation. Do not treat this project as compatible with that host until its exact product URL, plan, and requirements have been verified.

## Project documentation

- [Final project documentation](docs/FINAL_PROJECT_DOCUMENTATION.md)
- [Current development status](docs/DEVELOPMENT_STATUS.md)
- [API activation guide](docs/API_ACTIVATION_GUIDE.md)
- [Deployment guide](docs/DEPLOYMENT_GUIDE.md)
- [Environment and secret setup](docs/ENVIRONMENT_SETUP.md)
- [Known limitations and launch blockers](docs/KNOWN_LIMITATIONS.md)
- [Phase 12 final test report](docs/FINAL_TEST_REPORT.md)
- [Development roadmap](docs/DEVELOPMENT_ROADMAP.md)
- [Development log](docs/DEVELOPMENT_LOG.md)

## Local development and verification

Use the Composer and npm lockfiles in this workspace. The application requires PHP `^8.2`, Laravel `^12.0`, and Node compatible with the installed Vite 7 release for asset builds. Use a local development database and never copy production credentials into source files.

```sh
composer install
npm ci
php artisan test
npm run build
```

For production variables, secret handling, migrations, worker/scheduler setup, and launch checks, follow the [deployment guide](docs/DEPLOYMENT_GUIDE.md) and [environment setup](docs/ENVIRONMENT_SETUP.md).
