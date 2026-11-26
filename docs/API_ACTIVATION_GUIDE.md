# ZENTRIX ASTRA — API Activation Guide

This guide covers optional Safe Navigation reputation lookups and the AI Agent. Neither integration is required for local sign-in, the dashboard, Scanner, Website Auditor, File Check, or Academy.

## Before adding credentials

1. Decide whether this is a commercial service and review the provider's current terms, pricing, request limits, privacy, and data-retention rules.
2. Keep credentials in the ignored local `.env` file during development or in the hosting platform's secret manager in production. Never paste keys into chat, screenshots, source code, or `.env.example`.
3. Do not expose `.env` through the web root, commit it, or print it in terminal output.
4. The admin integration-status page reports credential presence only. Run `php artisan astra:check-integrations` to authenticate the OpenRouter key and check the exact NVIDIA free endpoint against the configured ZDR mode; it prints no credentials. This command does not test OpenRouter inference.

## Safe Navigation

Safe Navigation is administrator-only and does not open or fetch submitted destinations. VirusTotal is the only active optional credentialed provider. OpenPhish has been retired; historical tables remain but are not queried or refreshed.

**Important data-sharing behavior:** VirusTotal may receive the normalized URL, including its path and query, after explicit acknowledgement. Configure credentials only when authorized to share those URLs. A provider returning no match does not prove a URL is safe.

Optional credential:

| Provider | Environment variable | Decision to make |
|---|---|---|
| VirusTotal | `VIRUSTOTAL_API_KEY` | The public API is not permitted for commercial products/services; confirm that the selected license permits the intended use. |

Add only approved values to local `.env` or the deployment secret store. Then clear Laravel's cached configuration and restart long-running app processes:

```powershell
php artisan config:clear
```

In the admin panel, review provider status and submit a non-sensitive URL you are authorized to check. A status of “Configured · not tested” is not a successful connectivity test. Do not use a sensitive URL when VirusTotal is enabled because it receives the URL for lookup.

## OpenRouter AI Agent

The AI Agent, File Check AI, and Academy AI share OpenRouter's chat-completions integration. Users can choose between `nvidia/nemotron-3-ultra-550b-a55b:free` (the default) and `nvidia/nemotron-3.5-super-vl:free` in the AI chat. The saved account selection is used by subsequent AI requests across these modules. Both are restricted to NVIDIA endpoints; model availability and rate limits can change, so responses are not guaranteed.

Set these variables privately:

```dotenv
OPENROUTER_API_KEY=your-private-key-added-locally
OPENROUTER_MODEL=nvidia/nemotron-3-ultra-550b-a55b:free
OPENROUTER_REQUIRE_ZDR=false
OPENROUTER_DATA_COLLECTION=allow
```

`OPENROUTER_MODEL` sets the default for accounts without a saved choice; users can select only the two listed free models in the AI chat. Requests discover an eligible endpoint and are restricted to NVIDIA, disable provider fallbacks, and set a zero price cap. Tools, web search, redirects, and streaming remain disabled. Each user must enable AI data sharing once in Profile before AI requests are sent; that choice is saved and can be revoked there, so no per-message agreement checkbox is needed. The profile explains that prompts may be retained and used for training under provider policies. Following explicit owner authorization, `OPENROUTER_REQUIRE_ZDR` defaults to `false` and `OPENROUTER_DATA_COLLECTION` defaults to `allow` so eligible free NVIDIA endpoints can be used. This permits NVIDIA to retain and use prompts for training under its policy. Do not send secrets or sensitive personal data. Setting `OPENROUTER_REQUIRE_ZDR=true` and `OPENROUTER_DATA_COLLECTION=deny` restores stricter restrictions but may make these endpoints unavailable.

File-record explanations share only file type, size, processing status, and VirusTotal counts—not bytes, filename, storage key, or hash.

The integration command validates the provider metadata but does not send an inference request. Only test with a short synthetic prompt after it reports an eligible endpoint. Clear cached configuration and restart the app/queue worker after changing `.env`. No NVIDIA/Nemotron credential can be used as an OpenRouter key.

## Production and other services

- Put production credentials in the host's secret manager, not in a deployed repository file.
- Use a supported SMTP provider for real password-reset delivery; the local `log` mailer is for development only.
- Queue workers, scheduler, HTTPS, durable private storage, backups, and restores are deployment requirements; API keys do not configure them.
- Follow [Environment Setup](./ENVIRONMENT_SETUP.md), [Deployment Guide](./DEPLOYMENT_GUIDE.md), and [API Requirements](./API_REQUIREMENTS.md) for the full setup and limitations.
