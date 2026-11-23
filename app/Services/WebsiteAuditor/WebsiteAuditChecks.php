<?php

namespace App\Services\WebsiteAuditor;

use App\Data\EndpointScanData;
use App\Data\HttpProbeResult;
use App\Data\ScanCheckResultData;
use App\Data\ScanFindingData;
use App\Enums\ScanCheckOutcome;
use App\Enums\SecurityFindingSeverity;
use App\Services\Scanner\WebsitePageProbe;
use DOMDocument;
use DOMElement;
use DOMXPath;

class WebsiteAuditChecks
{
    public function __construct(private readonly WebsitePageProbe $pageProbe) {}

    /**
     * @return list<ScanCheckResultData>
     */
    public function run(string $hostname, string $address, EndpointScanData $endpoints): array
    {
        $page = $endpoints->https->successful
            ? $this->pageProbe->fetchDocument($hostname, $address)
            : new HttpProbeResult(false, null, [], [], $endpoints->https->errorCode ?? 'https_unavailable', null);

        return [
            $this->websiteInformation($page),
            $this->mixedContent($page),
            $this->cookieSecurity($endpoints->https, $page),
        ];
    }

    private function websiteInformation(HttpProbeResult $page): ScanCheckResultData
    {
        if (! $page->successful || $page->body === '') {
            return new ScanCheckResultData(
                'website_information',
                ScanCheckOutcome::Inconclusive,
                'Website document details were unavailable; the response body was not retained.',
                [
                    'status_code' => $page->statusCode,
                    'content_type' => $page->headers['content-type'] ?? null,
                    'failure_code' => $page->errorCode ?? 'empty_document',
                ],
            );
        }

        $document = $this->parseDocument($page->body);
        $title = $this->xpathText($document, '//title');
        $description = $this->xpathAttribute(
            $document,
            "//meta[translate(@name,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz')='description']",
            'content',
        );

        return new ScanCheckResultData(
            'website_information',
            ScanCheckOutcome::Passed,
            'Retrieved a bounded HTML document over the verified HTTPS connection.',
            [
                'status_code' => $page->statusCode,
                'content_type' => $page->headers['content-type'] ?? null,
                'title' => $this->limitText($title, 160),
                'meta_description' => $this->limitText($description, 240),
                'document_bytes_inspected' => strlen($page->body),
            ],
        );
    }

    private function mixedContent(HttpProbeResult $page): ScanCheckResultData
    {
        if (! $page->successful || $page->body === '') {
            return new ScanCheckResultData(
                'mixed_content',
                ScanCheckOutcome::Inconclusive,
                'Mixed-content checks require an available HTTPS HTML document.',
                ['failure_code' => $page->errorCode ?? 'html_document_unavailable'],
            );
        }

        $document = $this->parseDocument($page->body);
        $xpath = new DOMXPath($document);
        $samples = [];
        $resourceCount = 0;
        $highImpactResource = false;

        foreach ($xpath->query('//*[@src or @href or @poster or @srcset]') ?: [] as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            foreach (['src', 'href', 'poster', 'srcset'] as $attribute) {
                if (
                    ! $node->hasAttribute($attribute)
                    || ! $this->isLoadableResource($node, $attribute)
                    || ! $this->containsHttpUrl($node->getAttribute($attribute))
                ) {
                    continue;
                }

                $resourceCount++;
                $tag = strtolower($node->tagName);
                $highImpactResource = $highImpactResource
                    || in_array($tag, ['script', 'iframe', 'frame', 'link', 'object', 'embed'], true);

                if (count($samples) < 10) {
                    $samples[] = ['element' => $tag, 'attribute' => $attribute];
                }
            }
        }

        foreach (array_merge(
            iterator_to_array($xpath->query('//*[@style]') ?: []),
            iterator_to_array($xpath->query('//style') ?: []),
        ) as $node) {
            $css = $node instanceof DOMElement
                ? ($node->hasAttribute('style') ? $node->getAttribute('style') : $node->textContent)
                : '';
            $cssReferences = preg_match_all('/url\(\s*([\'"]?)http:\\/\\//i', $css);
            $cssReferences += preg_match_all('/@import\s+([\'"])http:\\/\\//i', $css);

            if ($cssReferences > 0) {
                $resourceCount += $cssReferences;
                $highImpactResource = true;

                if (count($samples) < 10) {
                    $samples[] = [
                        'element' => strtolower($node->tagName),
                        'attribute' => $node->hasAttribute('style') ? 'style' : 'css',
                    ];
                }
            }
        }

        if ($resourceCount === 0) {
            return new ScanCheckResultData(
                'mixed_content',
                ScanCheckOutcome::Passed,
                'No insecure HTTP subresource references were observed in the inspected HTML.',
                ['insecure_resource_count' => 0, 'document_bytes_inspected' => strlen($page->body)],
            );
        }

        $finding = new ScanFindingData(
            ruleKey: 'website_mixed_content',
            title: 'Insecure HTTP resources referenced by the HTTPS page',
            description: 'The inspected HTTPS document references '.$resourceCount.' resource(s) using HTTP. Browsers may block or downgrade these resources, and any resource that loads can weaken the integrity or confidentiality expected from the HTTPS page.',
            severity: $highImpactResource ? SecurityFindingSeverity::Medium : SecurityFindingSeverity::Low,
            evidence: [
                'insecure_resource_count' => $resourceCount,
                'resource_samples' => $samples,
            ],
            recommendedAction: 'Serve each referenced resource over HTTPS, update the page to use HTTPS URLs, or remove resources that are no longer needed.',
        );

        return new ScanCheckResultData(
            'mixed_content',
            ScanCheckOutcome::Finding,
            'Insecure HTTP subresource references were observed in the HTTPS document.',
            ['insecure_resource_count' => $resourceCount, 'resource_samples' => $samples],
            [$finding],
        );
    }

    private function cookieSecurity(HttpProbeResult $https, HttpProbeResult $page): ScanCheckResultData
    {
        $headers = array_slice(array_values(array_unique([
            ...$https->setCookieHeaders,
            ...$page->setCookieHeaders,
        ])), 0, 20);
        $truncated = $https->setCookieHeadersTruncated || $page->setCookieHeadersTruncated;

        if ($headers === []) {
            return new ScanCheckResultData(
                'cookie_security',
                ScanCheckOutcome::Inconclusive,
                $truncated
                    ? 'Set-Cookie response headers exceeded the inspection limit; cookie attributes could not be assessed reliably.'
                    : 'No Set-Cookie headers were observed on the sampled HTTPS responses; cookie attributes could not be assessed.',
                ['observed_cookie_count' => 0, 'sample_truncated' => $truncated],
            );
        }

        $missing = ['Secure' => 0, 'HttpOnly' => 0, 'SameSite' => 0];

        foreach ($headers as $header) {
            $attributes = array_slice(explode(';', $header), 1);
            $names = [];

            foreach ($attributes as $attribute) {
                [$name] = explode('=', trim($attribute), 2);
                $normalizedName = strtolower(trim($name));
                $names[$normalizedName] = true;
            }

            foreach (['secure' => 'Secure', 'httponly' => 'HttpOnly', 'samesite' => 'SameSite'] as $name => $label) {
                if (! isset($names[$name])) {
                    $missing[$label]++;
                }
            }
        }

        $evidence = [
            'observed_cookie_count' => count($headers),
            'sample_truncated' => $truncated,
            'cookies_missing_secure' => $missing['Secure'],
            'cookies_missing_httponly' => $missing['HttpOnly'],
            'cookies_missing_samesite' => $missing['SameSite'],
        ];
        $findings = [];

        if ($missing['Secure'] > 0) {
            $findings[] = new ScanFindingData(
                'website_cookie_secure',
                'Cookie without the Secure attribute observed',
                $missing['Secure'].' of '.count($headers).' observed cookie(s) did not include Secure. This allows a browser to send those cookies over an unencrypted HTTP connection if such a route is visited.',
                SecurityFindingSeverity::Medium,
                ['cookies_missing_secure' => $missing['Secure'], 'observed_cookie_count' => count($headers)],
                'Set Secure on cookies that should only be transmitted over HTTPS, especially authentication and session cookies.',
            );
        }

        if ($missing['HttpOnly'] > 0) {
            $findings[] = new ScanFindingData(
                'website_cookie_httponly',
                'Cookie without the HttpOnly attribute observed',
                $missing['HttpOnly'].' of '.count($headers).' observed cookie(s) did not include HttpOnly. Such cookies may be readable by page scripts; this may be intentional for client-side state.',
                SecurityFindingSeverity::Low,
                ['cookies_missing_httponly' => $missing['HttpOnly'], 'observed_cookie_count' => count($headers)],
                'Set HttpOnly on session and authentication cookies; retain script access only for cookies that explicitly require it.',
            );
        }

        if ($missing['SameSite'] > 0) {
            $findings[] = new ScanFindingData(
                'website_cookie_samesite',
                'Cookie without a SameSite attribute observed',
                $missing['SameSite'].' of '.count($headers).' observed cookie(s) did not include SameSite, so browser cross-site sending behavior may be less restricted.',
                SecurityFindingSeverity::Low,
                ['cookies_missing_samesite' => $missing['SameSite'], 'observed_cookie_count' => count($headers)],
                'Choose SameSite=Lax or Strict where possible; use SameSite=None only when cross-site use is required and pair it with Secure.',
            );
        }

        return new ScanCheckResultData(
            'cookie_security',
            $truncated ? ScanCheckOutcome::Inconclusive : ($findings === [] ? ScanCheckOutcome::Passed : ScanCheckOutcome::Finding),
            $truncated
                ? 'Cookie findings reflect only the sampled Set-Cookie headers; additional headers were not evaluated.'
                : ($findings === []
                ? 'All sampled cookies included Secure, HttpOnly, and SameSite attributes.'
                : 'One or more sampled cookies were missing security attributes.'),
            $evidence,
            $findings,
        );
    }

    private function containsHttpUrl(string $value): bool
    {
        return preg_match('/(?:^|[\s(,])http:\\/\\//i', $value) === 1;
    }

    private function isLoadableResource(DOMElement $element, string $attribute): bool
    {
        $tag = strtolower($element->tagName);

        return match ($attribute) {
            'src' => in_array($tag, ['audio', 'embed', 'frame', 'iframe', 'img', 'input', 'script', 'source', 'track', 'video'], true),
            'srcset' => in_array($tag, ['img', 'source'], true),
            'poster' => $tag === 'video',
            'href' => $tag === 'link' && count(array_intersect(
                preg_split('/\s+/', strtolower($element->getAttribute('rel')), -1, PREG_SPLIT_NO_EMPTY) ?: [],
                ['stylesheet', 'preload', 'modulepreload', 'icon', 'manifest', 'prefetch'],
            )) > 0,
            default => false,
        };
    }

    private function parseDocument(string $html): DOMDocument
    {
        $previousErrorMode = libxml_use_internal_errors(true);

        try {
            $document = new DOMDocument;
            $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

            return $document;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorMode);
        }
    }

    private function xpathText(DOMDocument $document, string $expression): ?string
    {
        $node = (new DOMXPath($document))->query($expression)?->item(0);

        return $node?->textContent;
    }

    private function xpathAttribute(DOMDocument $document, string $expression, string $attribute): ?string
    {
        $node = (new DOMXPath($document))->query($expression)?->item(0);

        return $node instanceof DOMElement ? $node->getAttribute($attribute) : null;
    }

    private function limitText(?string $value, int $limit): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');

        return mb_substr($value, 0, $limit);
    }
}
