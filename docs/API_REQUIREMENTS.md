# ZENTRIX ASTRA API and External Service Requirements

**Status:** VirusTotal API v3 is integrated for URL reputation and SHA-256 file-hash lookups. OpenRouter supports free NVIDIA Nemotron 3 Ultra (default) and Nemotron 3.5 Super VL, selected in the AI chat and persisted to the account. Routing remains NVIDIA-only, with fallbacks disabled and a zero-price cap. The local `.env` sets `OPENROUTER_DATA_COLLECTION=allow` after the owner's explicit authorization; this permits the NVIDIA provider to retain and use prompts for training under its policy. `.env.example` and the application default remain `deny`. Strict-ZDR eligibility for the free NVIDIA endpoints is not established by current metadata and is disabled locally. Harmless live completions succeeded for both selected models. Restore `OPENROUTER_REQUIRE_ZDR=true` and `OPENROUTER_DATA_COLLECTION=deny` to reinstate those restrictions, subject to endpoint availability. File Check sends message text to OpenRouter only after explicit account consent, and its file-record explanation sends only minimized metadata and VirusTotal counts, never uploaded bytes, filename, storage key, or hash. Academy administrators can generate privately stored HTML and PDF study materials with signed share links. OpenPhish runtime integration has been retired; its historical migration and tables are retained without refresh or lookup behavior.

## 1. Current boundary

The implemented foundation and Phases 5-7, 10, and 11 require no external API credential. The Academy content is original and stored locally; quiz grading and learning progress use the application database. VirusTotal and the AI adapter are optional. The retired OpenPhish integration is not used by current application workflows.

The application is a Blade web app. Use same-origin session-authenticated routes with CSRF protection for browser interactions. Do not add a public API merely for internal UI behavior. If a later consumer needs an API, define versioning, authentication, rate limits, authorization, and error contracts in that phase.

## 2. Potential integrations and decision gates

| Capability | Need | Current status | Provider/cost/credential decision |
|---|---|---|---|
| Central AI Agent | Answer questions and explain owner-selected assessment evidence without generating or changing findings. | Both free NVIDIA models pass live endpoint discovery and harmless completions; the chat selector persists a validated choice and keeps the conversation thread. | OpenRouter is the configured provider. Exact variables: `OPENROUTER_API_KEY`, `OPENROUTER_MODEL`, `OPENROUTER_REQUIRE_ZDR`, and `OPENROUTER_DATA_COLLECTION`. |
| Cybersecurity Academy | Deliver local lessons, grade knowledge checks, save account-owned progress, and generate downloadable learning guides. | Course generation remains available; administrators can create validated HTML/PDF materials in private storage and share them through signed links. | Uses the existing free Nemotron/OpenRouter integration and installed DomPDF package; no paid service or new package is needed. |
| Safe Navigation reputation | Reputation sources can classify submitted URLs but may return incomplete, stale, or inconclusive evidence. | VirusTotal is the only active URL reputation provider and remains optional. | Configure `VIRUSTOTAL_API_KEY` only when permitted by the provider's terms and the product's data-sharing policy. |
| Mail delivery | Phase 3 password-reset links are sent through Laravel's notification/password-broker system. | Local development uses Laravel's existing `log` mail transport (`MAIL_MAILER=log`); the reset link is written to the local application log. No SMTP provider is configured or required for local testing. | Before real delivery in production, the owner must select a mail provider and supply its SMTP credentials through the deployment secret store using Laravel's `MAIL_*` variables. Verify the selected service's current official docs, terms, pricing, and data handling. Do not use the log transport in production because it records reset links in logs. |

`VIRUSTOTAL_API_KEY` is optional and its value is never shown in the UI or documentation. `OPENROUTER_MODEL` can be changed in `.env` to an exact model variant ending in `:free`; endpoint availability and free-tier rate limits can change. Cross-model routing aliases are rejected. No paid fallback is permitted. Local password reset uses the existing log mailer; real delivery requires a later provider decision.

### Central AI Agent (Phase 9)

The adapter follows OpenRouter's `POST https://openrouter.ai/api/v1/chat/completions` endpoint and sends the user's saved choice from the two-model free NVIDIA allowlist (Nemotron 3 Ultra by default), bounded messages, `stream: false`, `max_tokens: 700`, and a Bearer API key. Routing is pinned to NVIDIA, fallback providers are disabled, and zero prices are enforced. Configuration presence is not treated as proof that the key or model is valid.

#### Model selection and cost policy

The integration check authenticates the key and validates the exact configured model's endpoint metadata. Completion requests are restricted to `provider.only: ["nvidia"]`, `allow_fallbacks: false`, and a zero price cap. `OPENROUTER_DATA_COLLECTION` controls the provider privacy preference and defaults to `deny`; the local `.env` is set to `allow` only because the owner explicitly authorized provider use of prompts for training. `OPENROUTER_REQUIRE_ZDR` defaults to `true`; when false, the app omits the `zdr` request parameter because current metadata does not establish strict-ZDR eligibility for the free NVIDIA endpoints. No other provider or model can be used as a fallback.

#### Privacy and data sent

The AI Agent sends the conversation prompt to OpenRouter only after the account has saved AI data-sharing consent in Profile. The model selector is in the chat composer; changing it validates provider eligibility and saves the account selection without changing the open thread. File Check's local message heuristics run without an external request; its optional AI explanation uses that same saved consent. For a stored file record, the explanation receives only its MIME type, size, processing status, and VirusTotal aggregate counts. It does not receive file bytes, original filename, storage key, or SHA-256 hash.

The app discloses that prompts and selected context may be sent to OpenRouter and NVIDIA, and that providers may retain prompts or use them for training under their policies. Consent is saved once per account in Profile and can be revoked there; no repeated per-message agreement checkbox is required. Agent context is limited to up to 8 recent conversation messages and an optional bounded summary of a selected finding. Academy generation is administrator-only and uses the administrator's saved consent and selected model.

Academy material generation validates structured plain-text AI output before rendering it into HTML and PDF. Files are stored on the private `academy-materials` disk; owner-only authenticated routes serve downloads, while stable signed share links expose only the generated learning material. Deleting a material removes its files and invalidates its link. Generated text is HTML-escaped, file sizes and formats are checked, and download responses do not reveal storage paths.

- Every request requires `provider.only: ["nvidia"]`, `provider.allow_fallbacks: false`, and zero prompt/completion price caps. `provider.data_collection` follows `OPENROUTER_DATA_COLLECTION` (default `deny`; currently `allow` locally by explicit authorization). With `allow`, prompts may be retained and used for training under the provider's policy. Strict-ZDR eligibility checks default to enabled; `OPENROUTER_REQUIRE_ZDR=false` omits that check for the selected endpoint, which is not strict-ZDR eligible.
- The app retries only idempotent endpoint-discovery GETs for transient failures. It does not automatically retry completion POSTs, avoiding duplicate inference requests; timeouts and rate limits are surfaced explicitly.
- No OpenRouter server tools, plugins, web search, streaming, or browser access are enabled. Uploaded file contents, hashes, secrets, other users' records, and full website responses are never sent.
- Finding context includes only allowlisted assessment fields and safe bounded evidence. Context is capped at 8,000 characters, history at 8 recent messages, user input at 4,000 characters, and output at 700 tokens/6,000 characters for Agent and File Check. Academy generation uses a 2,500-token budget with reasoning disabled; all requests retain the same NVIDIA-only and zero-price restrictions.
- OpenRouter documents that it stores request metadata and does not routinely log prompts/completions unless opted in, but it forwards prompt data to the model provider. Provider retention policies vary. Its ZDR documentation includes legal/CSAM screening exceptions and explains that some endpoint availability changes when ZDR-only routing is enforced. Review [data collection](https://openrouter.ai/docs/guides/privacy/data-collection), [provider logging](https://openrouter.ai/docs/guides/privacy/provider-logging), and [zero data retention](https://openrouter.ai/docs/guides/features/zdr) before sending sensitive content. The application cannot guarantee a model's factual accuracy or prevent all model-level prompt injection.
- Application conversations are owner-scoped and deleted after 30 days or earlier by the user. App logs contain sanitized status/model codes only, not prompt or response bodies.

#### OpenRouter credential checkpoint

1. Sign in at [OpenRouter](https://openrouter.ai/) and create an OpenRouter API key on the [API Keys page](https://openrouter.ai/keys). Never use a key from a different provider.
2. Keep the key only in the ignored local `.env` or deployment secret store. `OPENROUTER_MODEL` sets the default model; only the two configured free NVIDIA models can be selected in the AI chat. Do not use cross-model routing aliases or configure a paid model.

```dotenv
OPENROUTER_API_KEY=your-private-key-added-locally
OPENROUTER_MODEL=nvidia/nemotron-3-ultra-550b-a55b:free
OPENROUTER_REQUIRE_ZDR=true
```

The `.env.example` contains the exact free model ID and defaults `OPENROUTER_REQUIRE_ZDR=true`. The AI sidebar's “Configured” label indicates that settings are present; it does not verify connectivity. Run `php artisan astra:check-integrations` to check the endpoint metadata. If free inference is rate-limited or unavailable, wait and retry rather than switching providers or models.

Official references: [OpenRouter authentication and key limits](https://openrouter.ai/docs/api_reference/authentication), [Chat Completions API](https://openrouter.ai/docs/api/api-reference/chat/create-a-chat-completion), [free model variant](https://openrouter.ai/docs/guides/routing/model-variants/free), [free-model limits](https://openrouter.ai/docs/api_reference/limits), and [pricing/fees FAQ](https://openrouter.ai/docs/faq#pricing-and-fees).

### Safe Navigation reputation providers (Phase 8)

The local Safe Navigation form requires an authorization attestation and explicit acknowledgement that the configured credentialed reputation provider may receive the normalized URL—including its path and query. The application does not fetch the submitted destination, follow its redirects, or open it. VirusTotal is the only active reputation provider. OpenPhish refresh and lookup behavior has been removed; legacy feed tables remain solely for compatibility and are not queried.

| Provider/product | Access and credential | Current free/payment terms (official pages) | Fit and limits |
|---|---|---|---|
| Google Web Risk Lookup (future option) | A Google Cloud project, API key, and billing would be required if a future adapter is approved. The current app has no adapter or configuration variable. | At research time, the official pricing page listed 100,000 Lookup calls/month free, then USD $0.50 per 1,000 calls above that; verify current pricing before any future implementation. | See the official [Lookup API](https://docs.cloud.google.com/web-risk/docs/lookup-api), [overview](https://docs.cloud.google.com/web-risk/docs/overview), and [pricing](https://cloud.google.com/web-risk/pricing). |
| VirusTotal public API | VirusTotal Community account and API key sent in the `x-apikey` header. Configure `VIRUSTOTAL_API_KEY`. | Public API is free, limited to 500 requests/day and 4/minute, and prohibited for commercial products/services. Premium use requires contacting sales; no public price was found in the reviewed docs. | Prefer the read-only [URL information endpoint](https://docs.virustotal.com/reference/url-info); do not submit/scan a URL unless separately approved. Docs: [getting started](https://docs.virustotal.com/reference/getting-started), [Public vs Premium](https://docs.virustotal.com/reference/public-vs-premium-api). |

Provider terms and product eligibility still apply. Credentialed URL lookups disclose the full normalized URL, including sensitive path/query data, to that provider. A lookup result is not a safety guarantee.

#### Configuration checkpoint

OpenPhish is retired and has no active configuration. Before enabling an optional credentialed provider, confirm whether ZENTRIX ASTRA is commercial/for-profit and whether disclosure of full URLs is acceptable. Keep approved credentials only in the ignored local `.env` or deployment secret manager; never paste values into chat:

```dotenv
VIRUSTOTAL_API_KEY=
```

The `.env.example` has only an optional blank VirusTotal credential placeholder. OpenPhish has no active environment variable or integration. The admin status page reports configuration, while `php artisan astra:check-integrations` makes live provider requests; neither command prints credential values. No credential values should be pasted into chat or source code.

## 3. Target assessment HTTP is not an unrestricted API

The Phase 5 scanner and Phase 6 Website Auditor make bounded outbound requests only to a DNS-verified hostname that the user attests they own or are authorized to assess. This is a controlled capability, not a general-purpose URL proxy.

For the implemented scanner:

- Require a verified ownership challenge and authorization attestation; accept normalized hostnames, not arbitrary URLs/IP addresses.
- Resolve A/AAAA records and reject the target if any answer is private, loopback, link-local, multicast, reserved, or otherwise outside the public IPv4/IPv6 ranges permitted by policy.
- Pin each cURL connection to a validated address using `CURLOPT_RESOLVE`; verify that the connected peer matches the pinned address. Do not follow redirects. Report whether HTTP appears to redirect to HTTPS on the same verified hostname without following the destination.
- The scanner uses HTTP HEAD requests only; the Website Auditor may make one additional HTTPS GET of the root page only after a verified HTTPS response. This GET accepts only HTML/XHTML, inspects at most 256 KiB, and aborts on a different content type or a larger body.
- Use requests only on ports 80 and 443, finite connect/request timeouts, verified TLS, no proxy, bounded response headers, per-user registration/verification/scan throttles, and a database-backed queue.
- Do not follow redirects or fetch HTML-referenced assets. Mixed-content inspection parses the bounded page locally with network access disabled.
- Cookie checks inspect only Set-Cookie attribute presence; never persist or report cookie values or names. A response without observed cookies is inconclusive, not a clean result.
- Store only allowlisted response headers, selected certificate metadata, bounded evidence, and sanitized failures; do not store response bodies or full network errors.
- Prefer an explicit egress allowlist/network policy in production. Do not let DNS changes or redirect chains bypass validation.
- Record clear errors and blocked/unavailable states. Never report a clean result when a request was blocked, timed out, or failed.

No external scanning service or API credential is required for these checks. Phase 5/6 tests use fake DNS/probe adapters and make no live requests. SQLite fresh migrations and feature tests passed, while a running MySQL server was not available to verify compatibility at runtime.

## 4. File Check data boundary

Phase 7 accepts valid uploaded files up to 10 MiB and stores them on a non-public local disk under generated names. The application records server-detected MIME type, byte size, original display name, processing state, and SHA-256. It does not execute, parse, decompress, render, download, or submit the contents/hash to a third party. A completed result is metadata/hash capture only, not a malware verdict. No external API key or antivirus package is required.

## 5. AI data and output contract

For the Phase 9 AI integration:

- Send only the minimum authorized selected finding needed for the requested explanation. Never send uploaded file contents, hashes, credentials, session tokens, or unrelated tenant/user data.
- Disclose the provider, eligible upstream provider, recent conversation history, and data flow to users and obtain per-message consent.
- Use a provider boundary, bounded request/context/output, timeouts, rate limits, provider-level spend caps, strict data-retention routing, and explicit failure states.
- Treat user text and persisted findings as untrusted prompt data. Do not provide tools, browsing, plugins, or access to unrelated records. Validate the response shape/size and render safely escaped plain text.
- Mark generated content as AI-generated. The model may explain evidence, but cannot create or change a finding's severity/verification state.
- Redact prompts and responses in application logs; store conversation history only under an approved retention policy.

## 6. Credential handling procedure

When a provider is genuinely required, stop before enabling a live integration and tell the project owner:

1. The exact provider/product and its role.
2. Why it is needed and what data it receives.
3. Verified current free-tier, payment, and subscription conditions.
4. Official website and documentation links.
5. The exact credential type and approved environment variable name.
6. How to add it to the ignored local `.env` or deployment secret store, never ordinary chat.

Do not invent credentials, commit secrets, print secret values, include them in screenshots, or install an SDK until compatibility, license, maintenance, cost, and approval are addressed. Document placeholders only; keep `.env` values private.

## 7. Credentials needed only for approved integrations

No external API key or credential is needed for Phases 0-7, Phase 8's local workflow and tests, or Phase 10 Academy. Provider-backed Safe Navigation lookups require confirmed commercial eligibility/data sharing. The Phase 9 adapter uses OpenRouter only and requires a private key plus a free model route; free inference may be rate-limited or unavailable. Paid fallbacks remain disabled.
