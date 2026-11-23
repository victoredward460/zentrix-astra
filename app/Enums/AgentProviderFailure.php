<?php

namespace App\Enums;

enum AgentProviderFailure: string
{
    case NotConfigured = 'not_configured';
    case InvalidCredentials = 'invalid_credentials';
    case InsufficientCredits = 'insufficient_credits';
    case SpendLimitReached = 'spend_limit_reached';
    case RateLimited = 'rate_limited';
    case ModelUnavailable = 'model_unavailable';
    case PrivacyPolicyUnavailable = 'privacy_policy_unavailable';
    case ProviderUnavailable = 'provider_unavailable';
    case RequestTimedOut = 'request_timed_out';
    case InvalidResponse = 'invalid_response';
    case RequestRejected = 'request_rejected';

    public function userMessage(?int $retryAfterSeconds = null): string
    {
        return match ($this) {
            self::NotConfigured => 'OpenRouter is not configured for free-only use. Configure OPENROUTER_API_KEY and set OPENROUTER_MODEL to an exact model variant ending in :free locally.',
            self::InvalidCredentials => 'OpenRouter rejected the configured API key (HTTP 401). Create or reissue an OpenRouter key in Account Settings, replace OPENROUTER_API_KEY privately in .env, clear Laravel configuration, and retry. No AI answer was generated.',
            self::InsufficientCredits => 'The OpenRouter account has insufficient credits. Check its billing settings; no AI answer was generated.',
            self::SpendLimitReached => 'The OpenRouter key or account spend limit was reached. Review its limits before retrying.',
            self::RateLimited => $retryAfterSeconds !== null
                ? "OpenRouter rate-limited this request. Retry in {$retryAfterSeconds} seconds; no AI answer was generated."
                : 'OpenRouter rate-limited this request. Wait before trying again; no AI answer was generated.',
            self::ModelUnavailable => 'OpenRouter has no available zero-cost endpoint for the configured free model, so no answer was generated. The application will not switch to a different or paid model.',
            self::PrivacyPolicyUnavailable => 'OpenRouter account privacy settings block the free NVIDIA endpoint. Check whether model training is allowed in your account settings. No AI answer was generated.',
            self::ProviderUnavailable => 'OpenRouter or its selected model provider is temporarily unavailable. No AI answer was generated.',
            self::RequestTimedOut => 'The OpenRouter request timed out. No AI answer was generated.',
            self::InvalidResponse => 'OpenRouter returned an unusable response. No AI answer was generated.',
            self::RequestRejected => 'OpenRouter rejected this request. Check the configured model and request limits.',
        };
    }
}
