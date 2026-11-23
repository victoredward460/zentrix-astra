<?php

namespace App\Http\Controllers\Scanner;

use App\Http\Controllers\Controller;
use App\Http\Requests\ScanSubmittedUrlRequest;
use App\Services\SafeNavigation\UrlNormalizer;
use App\Services\SafeNavigation\UrlReputationProviderException;
use App\Services\SafeNavigation\VirusTotalProvider;
use Illuminate\Http\RedirectResponse;

class SubmittedUrlController extends Controller
{
    public function store(
        ScanSubmittedUrlRequest $request,
        UrlNormalizer $normalizer,
        VirusTotalProvider $virusTotal,
    ): RedirectResponse {
        $normalizedUrl = $normalizer->normalize($request->validated('url'));

        if (! $virusTotal->isConfigured()) {
            return back()->withInput()->withErrors([
                'url' => 'VirusTotal is not configured. Add VIRUSTOTAL_API_KEY privately in .env.',
            ]);
        }

        try {
            $result = $virusTotal->assess($normalizedUrl);
        } catch (UrlReputationProviderException $exception) {
            $message = match ($exception->reason) {
                'rate_limited' => 'VirusTotal is rate-limited. Wait and try again.',
                'invalid_api_key' => 'VirusTotal rejected the configured API key.',
                'forbidden' => 'VirusTotal denied this lookup for the configured account.',
                'connection_failed' => 'VirusTotal could not be reached.',
                'not_found' => 'VirusTotal has no report for this URL yet.',
                default => 'VirusTotal could not complete the URL analysis.',
            };

            return back()->withInput()->withErrors(['url' => $message]);
        }

        return back()->with('url_scan_result', [
            'url' => $normalizedUrl,
            'classification' => $result->classification->value,
            'threat_types' => $result->threatTypes,
            'evidence' => $result->evidence,
            'provider' => $result->providerName,
        ]);
    }
}
