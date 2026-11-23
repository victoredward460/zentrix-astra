<?php

namespace App\Http\Controllers\WebsiteAuditor;

use App\Http\Controllers\Controller;
use App\Http\Requests\RequestDirectWebsiteAudit;
use App\Services\SafeNavigation\UrlNormalizer;
use App\Services\Scanner\Exceptions\DnsLookupFailed;
use App\Services\Scanner\Exceptions\PublicTargetRejected;
use App\Services\Scanner\PublicTargetResolver;
use App\Services\Scanner\Checks\DnsConfigurationCheck;
use App\Services\Scanner\Checks\HttpsRedirectCheck;
use App\Services\Scanner\Checks\SecurityHeadersCheck;
use App\Services\Scanner\Checks\WebEndpointCheck;
use App\Services\WebsiteAuditor\WebsiteAuditChecks;
use Illuminate\Http\RedirectResponse;

class DirectWebsiteAuditController extends Controller
{
    public function store(
        RequestDirectWebsiteAudit $request,
        UrlNormalizer $normalizer,
        PublicTargetResolver $resolver,
        DnsConfigurationCheck $dnsCheck,
        WebEndpointCheck $endpointCheck,
        HttpsRedirectCheck $redirectCheck,
        SecurityHeadersCheck $headersCheck,
        WebsiteAuditChecks $websiteAuditChecks,
    ): RedirectResponse {
        $url = $normalizer->normalize($request->validated('url'));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        try {
            $addresses = $resolver->resolve($host);
        } catch (PublicTargetRejected|DnsLookupFailed) {
            return back()->withInput()->withErrors([
                'url' => 'The hostname could not be safely resolved to public addresses.',
            ]);
        }

        sort($addresses, SORT_STRING);
        $address = $addresses[0];
        $dns = $dnsCheck->run($host, $addresses);
        $endpoints = $endpointCheck->run($host, $address);
        $results = [
            $dns,
            ...$endpoints->checkResults,
            $redirectCheck->run($host, $endpoints),
            $headersCheck->run($endpoints),
            ...$websiteAuditChecks->run($host, $address, $endpoints),
        ];

        $findings = [];
        foreach ($results as $result) {
            foreach ($result->findings as $finding) {
                $findings[] = [
                    'severity' => $finding->severity->value,
                    'title' => $finding->title,
                    'recommended_action' => $finding->recommendedAction,
                ];
            }
        }

        return back()->with('direct_audit_result', [
            'url' => $url,
            'hostname' => $host,
            'checks' => array_map(
                fn ($result): array => [
                    'check_key' => $result->checkKey,
                    'outcome' => $result->outcome->value,
                    'summary' => $result->summary,
                ],
                $results,
            ),
            'findings' => $findings,
        ]);
    }
}
