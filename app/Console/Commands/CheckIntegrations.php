<?php

namespace App\Console\Commands;

use App\Services\AI\AgentChatProvider;
use App\Services\AI\OpenRouterEndpointDiscovery;
use App\Services\SafeNavigation\ProviderStatusService;
use App\Services\SafeNavigation\UrlReputationProviderException;
use App\Services\SafeNavigation\UrlReputationProviderRegistry;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class CheckIntegrations extends Command
{
    protected $signature = 'astra:check-integrations {--configuration-only : Do not make outbound provider requests}';

    protected $description = 'Check integration configuration and connectivity without displaying credentials';

    public function handle(
        AgentChatProvider $agentProvider,
        OpenRouterEndpointDiscovery $endpointDiscovery,
        ProviderStatusService $providerStatusService,
        UrlReputationProviderRegistry $providerRegistry,
    ): int {
        $configurationOnly = (bool) $this->option('configuration-only');
        $failures = 0;
        $rows = [];

        $rows[] = $this->checkOpenRouter($agentProvider, $endpointDiscovery, $configurationOnly, $failures);

        foreach ($providerStatusService->all() as $status) {
            $provider = $providerRegistry->keyed()[$status['key']] ?? null;

            if (! $status['credential_configured']) {
                $rows[] = [$status['name'], 'Missing', 'Not tested'];

                continue;
            }

            if ($provider === null) {
                $rows[] = [$status['name'], 'Present', 'Adapter unavailable'];
                $failures++;

                continue;
            }

            if ($configurationOnly) {
                $rows[] = [$status['name'], 'Present', 'Not tested'];

                continue;
            }

            try {
                $provider->assess('https://example.com/');
                $rows[] = [$status['name'], 'Present', 'Provider responded'];
            } catch (UrlReputationProviderException) {
                $rows[] = [$status['name'], 'Present', 'Request failed'];
                $failures++;
            }
        }

        $this->table(['Provider', 'Configuration', 'Connection'], $rows);

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  int  $failures  Updated when a configured request fails.
     * @return array{string, string, string}
     */
    private function checkOpenRouter(
        AgentChatProvider $provider,
        OpenRouterEndpointDiscovery $endpointDiscovery,
        bool $configurationOnly,
        int &$failures,
    ): array
    {
        if (! $provider->isConfigured()) {
            return ['OpenRouter', 'Missing or invalid', 'Not tested'];
        }

        if ($configurationOnly) {
            return ['OpenRouter', 'Present', 'Not tested'];
        }

        $apiKey = config('services.openrouter.api_key');
        $model = $provider->model();

        try {
            $request = Http::acceptJson()
                ->withToken(is_string($apiKey) ? $apiKey : '')
                ->connectTimeout(3)
                ->timeout(8)
                ->withoutRedirecting();

            $keyResponse = $request->get('https://openrouter.ai/api/v1/key');
        } catch (ConnectionException) {
            $failures++;

            return ['OpenRouter', 'Present', 'Connection failed'];
        }

        if (! $keyResponse->successful()) {
            $failures++;

            return ['OpenRouter', 'Present', 'Request rejected (HTTP '.$keyResponse->status().')'];
        }

        try {
            $eligibleProviders = $endpointDiscovery->eligibleProviders((string) $model);
        } catch (\App\Exceptions\AgentProviderException $exception) {
            $failures++;

            return ['OpenRouter', 'Present', 'Key authenticated; '.$exception->userMessage()];
        }

        $privacyStatus = config('ai.openrouter.require_zdr', false)
            ? 'free strict-ZDR NVIDIA endpoint metadata found; inference not tested'
            : 'free NVIDIA endpoint metadata found; strict ZDR disabled; inference not tested';

        return ['OpenRouter', 'Present', 'Key authenticated; '.$privacyStatus];
    }
}
