<?php

namespace App\Services\AI;

use App\Data\AgentProviderReply;
use App\Enums\AgentProviderFailure;
use App\Exceptions\AgentProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenRouterChatProvider implements AgentChatProvider
{
    public function __construct(private readonly OpenRouterEndpointDiscovery $endpointDiscovery) {}

    public function isConfigured(): bool
    {
        $key = config('services.openrouter.api_key');
        $model = $this->model();
        $baseUrl = rtrim((string) config('services.openrouter.base_url'), '/');
        $dataCollection = config('ai.openrouter.data_collection', 'deny');

        return is_string($key)
            && trim($key) !== ''
            && $baseUrl === 'https://openrouter.ai/api/v1'
            && is_string($model)
            && preg_match('#^[a-z0-9._-]+/[a-z0-9._-]+:free$#i', $model) === 1
            && is_string($dataCollection)
            && in_array($dataCollection, ['allow', 'deny'], true);
    }

    public function model(): ?string
    {
        $model = config('services.openrouter.model');

        return is_string($model) && trim($model) !== '' ? trim($model) : null;
    }

    public function complete(
        array $messages,
        ?int $maxTokens = null,
        bool $disableReasoning = false,
        ?string $modelOverride = null,
    ): AgentProviderReply
    {
        if (! $this->isConfigured()) {
            throw new AgentProviderException(AgentProviderFailure::NotConfigured);
        }

        $model = $this->resolveModel($modelOverride);

        if (! is_string($model)) {
            throw new AgentProviderException($modelOverride === null
                ? AgentProviderFailure::NotConfigured
                : AgentProviderFailure::ModelUnavailable);
        }

        $eligibleProviders = $this->endpointDiscovery->eligibleProviders($model);

        if (! in_array('nvidia', $eligibleProviders, true)) {
            throw new AgentProviderException(AgentProviderFailure::ModelUnavailable);
        }

        $providerPreferences = [
            'only' => ['nvidia'],
            'allow_fallbacks' => false,
            'data_collection' => config('ai.openrouter.data_collection', 'deny'),
            'max_price' => [
                'prompt' => 0,
                'completion' => 0,
            ],
        ];

        if (config('ai.openrouter.require_zdr', true)) {
            $providerPreferences['zdr'] = true;
        }

        $request = [
            'model' => $model,
            'messages' => $messages,
            'stream' => false,
            'temperature' => 0.2,
            'max_tokens' => $maxTokens ?? (int) config('ai.openrouter.max_tokens'),
            'provider' => $providerPreferences,
        ];

        if (
            $disableReasoning
            || in_array($model, (array) config('ai.openrouter.reasoning_disabled_models', []), true)
        ) {
            $request['reasoning'] = ['effort' => 'none'];
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->withToken((string) config('services.openrouter.api_key'))
                ->connectTimeout((int) config('ai.openrouter.connect_timeout'))
                ->timeout((int) config('ai.openrouter.timeout'))
                ->withoutRedirecting()
                ->post(rtrim((string) config('services.openrouter.base_url'), '/').'/chat/completions', $request);
        } catch (ConnectionException $exception) {
            $failure = str_contains(mb_strtolower($exception->getMessage()), 'timeout')
                || str_contains(mb_strtolower($exception->getMessage()), 'timed out')
                ? AgentProviderFailure::RequestTimedOut
                : AgentProviderFailure::ProviderUnavailable;
            Log::warning('OpenRouter chat request could not connect.', [
                'failure_code' => $failure->value,
                'model' => $model,
            ]);

            throw new AgentProviderException($failure);
        }

        if (! $response->successful()) {
            $failure = $this->failureForResponse($response);
            Log::warning('OpenRouter chat request failed.', [
                'failure_code' => $failure->value,
                'model' => $model,
                'status' => $response->status(),
                'retry_after_seconds' => $this->retryAfterSeconds($response),
            ]);

            throw new AgentProviderException($failure, $this->retryAfterSeconds($response));
        }

        $responseModel = $response->json('model');

        if (! is_string($responseModel) || trim($responseModel) === '') {
            Log::warning('OpenRouter returned a response without a model identifier.', [
                'failure_code' => AgentProviderFailure::InvalidResponse->value,
                'model' => $model,
                'status' => $response->status(),
            ]);

            throw new AgentProviderException(AgentProviderFailure::InvalidResponse);
        }

        if ($this->baseModelId(trim($responseModel)) !== $this->baseModelId($model)) {
            Log::warning('OpenRouter returned a response for a different model.', [
                'failure_code' => AgentProviderFailure::ModelUnavailable->value,
                'model' => $model,
                'response_model' => trim($responseModel),
                'status' => $response->status(),
            ]);

            throw new AgentProviderException(AgentProviderFailure::ModelUnavailable);
        }

        $content = $response->json('choices.0.message.content');

        if (
            ! is_string($content)
            || trim($content) === ''
            || mb_strlen($content) > (int) config('ai.message.max_output_chars')
        ) {
            Log::warning('OpenRouter returned an unusable chat response.', [
                'failure_code' => AgentProviderFailure::InvalidResponse->value,
                'model' => $model,
                'status' => $response->status(),
            ]);

            throw new AgentProviderException(AgentProviderFailure::InvalidResponse);
        }

        return new AgentProviderReply(
            content: trim($content),
            provider: 'openrouter',
            model: trim($responseModel),
            promptTokens: $this->nonNegativeInteger($response->json('usage.prompt_tokens')),
            completionTokens: $this->nonNegativeInteger($response->json('usage.completion_tokens')),
        );
    }

    private function failureForResponse(Response $response): AgentProviderFailure
    {
        $providerMessage = $response->json('error.message');

        if (
            $response->status() === 404
            && is_string($providerMessage)
            && (
                str_contains(mb_strtolower($providerMessage), 'zero data retention')
                || str_contains(mb_strtolower($providerMessage), 'data_collection')
                || str_contains(mb_strtolower($providerMessage), 'zdr')
                || str_contains(mb_strtolower($providerMessage), 'data policy')
            )
        ) {
            return AgentProviderFailure::PrivacyPolicyUnavailable;
        }

        return $this->failureForStatus($response->status());
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
            400 => AgentProviderFailure::RequestRejected,
            default => $status >= 500
                ? AgentProviderFailure::ProviderUnavailable
                : AgentProviderFailure::RequestRejected,
        };
    }

    private function nonNegativeInteger(mixed $value): ?int
    {
        return is_int($value) && $value >= 0 ? $value : null;
    }

    private function retryAfterSeconds(Response $response): ?int
    {
        $retryAfter = $response->header('Retry-After');

        return is_string($retryAfter) && ctype_digit($retryAfter)
            ? (int) $retryAfter
            : null;
    }

    private function baseModelId(string $model): string
    {
        return preg_replace('/:free$/', '', $model) ?? $model;
    }

    private function resolveModel(?string $modelOverride): ?string
    {
        if ($modelOverride === null) {
            return $this->model();
        }

        return in_array($modelOverride, array_keys((array) config('ai.openrouter.models', [])), true)
            ? $modelOverride
            : null;
    }
}
