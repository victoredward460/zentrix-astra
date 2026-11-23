<?php

namespace App\Services\AI;

use App\Enums\AgentProviderFailure;
use App\Exceptions\AgentProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenRouterEndpointDiscovery
{
    private const NVIDIA_PROVIDER = 'nvidia';

    /**
     * @return list<string> Provider slugs with free endpoints meeting the configured privacy policy.
     */
    public function eligibleProviders(string $model): array
    {
        if (! preg_match('#^[a-z0-9._-]+/[a-z0-9._-]+:free$#i', $model)) {
            throw new AgentProviderException(AgentProviderFailure::ModelUnavailable);
        }

        [$author, $slug] = explode('/', $model, 2);
        $baseUrl = rtrim((string) config('services.openrouter.base_url'), '/');
        $modelUrl = $baseUrl.'/models/'.$author.'/'.$slug.'/endpoints';
        $modelData = $this->cachedData(
            'openrouter.model-endpoints.'.hash('sha256', $modelUrl),
            $modelUrl,
            now()->addMinute(),
        );
        $modelEndpoints = $modelData['endpoints'] ?? null;

        if (! is_array($modelEndpoints) || $modelEndpoints === []) {
            throw new AgentProviderException(AgentProviderFailure::ModelUnavailable);
        }

        $freeEndpoints = array_values(array_filter(
            $modelEndpoints,
            fn (mixed $endpoint): bool => is_array($endpoint)
                && is_string($endpoint['provider_name'] ?? null)
                && mb_strtolower(trim($endpoint['provider_name'])) === 'nvidia'
                && mb_strtolower((string) ($endpoint['tag'] ?? '')) === self::NVIDIA_PROVIDER
                && $this->hasZeroPrice($endpoint['pricing'] ?? null),
        ));

        if ($freeEndpoints === []) {
            throw new AgentProviderException(AgentProviderFailure::ModelUnavailable);
        }

        if (! config('ai.openrouter.require_zdr', true)) {
            return [self::NVIDIA_PROVIDER];
        }

        $zdrCacheKey = 'openrouter.zdr-endpoints.'.hash('sha256', $baseUrl.'|'.$this->baseModelId($model));
        $zdrData = Cache::remember($zdrCacheKey, now()->addMinutes(5), function () use ($baseUrl, $model): array {
            $endpoints = $this->requestData($baseUrl.'/endpoints/zdr');

            return array_values(array_filter(
                $endpoints,
                fn (mixed $endpoint): bool => is_array($endpoint)
                    && is_string($endpoint['model_id'] ?? null)
                    && $this->baseModelId($endpoint['model_id']) === $this->baseModelId($model),
            ));
        });

        $eligibleProviders = [];

        foreach ($freeEndpoints as $freeEndpoint) {
            foreach ($zdrData as $zdrEndpoint) {
                if (
                    is_array($zdrEndpoint)
                    && ($zdrEndpoint['safety_retention'] ?? null) === false
                    && $this->sameEndpoint($model, $freeEndpoint, $zdrEndpoint)
                ) {
                    $eligibleProviders[] = self::NVIDIA_PROVIDER;
                    break;
                }
            }
        }

        $eligibleProviders = array_values(array_unique(array_filter($eligibleProviders)));

        if ($eligibleProviders === []) {
            throw new AgentProviderException(AgentProviderFailure::PrivacyPolicyUnavailable);
        }

        return $eligibleProviders;
    }

    /**
     * @return array<mixed>
     */
    private function cachedData(string $key, string $url, \DateTimeInterface $expiresAt): array
    {
        return Cache::remember($key, $expiresAt, fn (): array => $this->requestData($url));
    }

    /**
     * @return array<mixed>
     */
    private function requestData(string $url): array
    {
        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $response = Http::acceptJson()
                    ->connectTimeout((int) config('ai.openrouter.connect_timeout'))
                    ->timeout((int) config('ai.openrouter.timeout'))
                    ->withoutRedirecting()
                    ->get($url);
            } catch (ConnectionException $exception) {
                if ($attempt === 0) {
                    usleep(250_000);

                    continue;
                }

                $failure = $this->failureForConnection($exception);
                Log::warning('OpenRouter endpoint discovery could not connect.', [
                    'failure_code' => $failure->value,
                ]);

                throw new AgentProviderException($failure);
            }

            if ($response->status() === 429) {
                $retryAfter = $this->retryAfterSeconds($response);

                if ($attempt === 0 && ($retryAfter === null || $retryAfter <= 1)) {
                    usleep($retryAfter === null ? 250_000 : max(1, $retryAfter) * 1_000_000);

                    continue;
                }

                Log::warning('OpenRouter endpoint discovery was rate-limited.', [
                    'retry_after_seconds' => $retryAfter,
                ]);

                throw new AgentProviderException(AgentProviderFailure::RateLimited, $retryAfter);
            }

            if (($response->serverError() || $response->status() === 408) && $attempt === 0) {
                usleep(250_000);

                continue;
            }

            if (! $response->successful()) {
                throw new AgentProviderException($this->failureForStatus($response->status()));
            }

            $data = $response->json('data');

            if (! is_array($data)) {
                throw new AgentProviderException(AgentProviderFailure::InvalidResponse);
            }

            return $data;
        }

        throw new AgentProviderException(AgentProviderFailure::ProviderUnavailable);
    }

    private function sameEndpoint(string $model, array $freeEndpoint, array $zdrEndpoint): bool
    {
        $zdrModel = $zdrEndpoint['model_id'] ?? null;
        $freeProvider = $freeEndpoint['provider_name'] ?? null;
        $zdrProvider = $zdrEndpoint['provider_name'] ?? null;

        return is_string($zdrModel)
            && $this->baseModelId($zdrModel) === $this->baseModelId($model)
            && is_string($freeProvider)
            && is_string($zdrProvider)
            && mb_strtolower(trim($freeProvider)) === mb_strtolower(trim($zdrProvider))
            && $this->normalizeTag($freeEndpoint['tag'] ?? null) === $this->normalizeTag($zdrEndpoint['tag'] ?? null);
    }

    private function baseModelId(string $model): string
    {
        return preg_replace('/:free$/', '', $model) ?? $model;
    }

    private function normalizeTag(mixed $tag): ?string
    {
        return is_string($tag) && trim($tag) !== ''
            ? mb_strtolower(trim($tag))
            : null;
    }

    private function hasZeroPrice(mixed $pricing): bool
    {
        if (! is_array($pricing)) {
            return false;
        }

        foreach (['prompt', 'completion'] as $key) {
            if (! isset($pricing[$key]) || ! is_numeric($pricing[$key]) || (float) $pricing[$key] !== 0.0) {
                return false;
            }
        }

        return true;
    }

    private function failureForConnection(ConnectionException $exception): AgentProviderFailure
    {
        $message = mb_strtolower($exception->getMessage());

        return str_contains($message, 'timeout') || str_contains($message, 'timed out')
            ? AgentProviderFailure::RequestTimedOut
            : AgentProviderFailure::ProviderUnavailable;
    }

    private function failureForStatus(int $status): AgentProviderFailure
    {
        return match ($status) {
            401 => AgentProviderFailure::InvalidCredentials,
            402 => AgentProviderFailure::InsufficientCredits,
            403 => AgentProviderFailure::SpendLimitReached,
            404 => AgentProviderFailure::ModelUnavailable,
            408 => AgentProviderFailure::RequestTimedOut,
            429 => AgentProviderFailure::RateLimited,
            default => $status >= 500
                ? AgentProviderFailure::ProviderUnavailable
                : AgentProviderFailure::RequestRejected,
        };
    }

    private function retryAfterSeconds(Response $response): ?int
    {
        $retryAfter = $response->header('Retry-After');

        return is_string($retryAfter) && ctype_digit($retryAfter)
            ? (int) $retryAfter
            : null;
    }
}
