<?php

namespace App\Services\Scanner;

use App\Data\ScanCheckResultData;
use App\Enums\FindingVerificationState;
use App\Enums\ScanCheckOutcome;
use App\Enums\ScanStatus;
use App\Enums\ScanType;
use App\Enums\SecurityFindingState;
use App\Models\ScanRun;
use App\Services\Scanner\Checks\DnsConfigurationCheck;
use App\Services\Scanner\Checks\HttpsRedirectCheck;
use App\Services\Scanner\Checks\SecurityHeadersCheck;
use App\Services\Scanner\Checks\VirusTotalHostnameCheck;
use App\Services\Scanner\Checks\WebEndpointCheck;
use App\Services\Scanner\Exceptions\DnsLookupFailed;
use App\Services\Scanner\Exceptions\PublicTargetRejected;
use App\Services\WebsiteAuditor\WebsiteAuditChecks;
use Illuminate\Support\Facades\DB;

class SecurityScanService
{
    public function __construct(
        private readonly PublicTargetResolver $targetResolver,
        private readonly DnsConfigurationCheck $dnsCheck,
        private readonly WebEndpointCheck $endpointCheck,
        private readonly HttpsRedirectCheck $redirectCheck,
        private readonly SecurityHeadersCheck $headersCheck,
        private readonly VirusTotalHostnameCheck $virusTotalCheck,
        private readonly WebsiteAuditChecks $websiteAuditChecks,
    ) {}

    public function execute(int $scanRunId): void
    {
        $startedAt = now();
        $transitioned = ScanRun::query()
            ->whereKey($scanRunId)
            ->where('status', ScanStatus::Queued->value)
            ->update([
                'status' => ScanStatus::Processing->value,
                'started_at' => $startedAt,
                'updated_at' => $startedAt,
            ]);

        if ($transitioned !== 1) {
            return;
        }

        $scanRun = ScanRun::query()->with('securityAsset')->findOrFail($scanRunId);
        $hostname = $scanRun->securityAsset->hostname;

        try {
            $addresses = $this->targetResolver->resolve($hostname);
        } catch (PublicTargetRejected) {
            $this->finish(
                $scanRun,
                [new ScanCheckResultData(
                    'target_safety',
                    ScanCheckOutcome::Blocked,
                    'The target was blocked because it did not resolve exclusively to public addresses.',
                    ['failure_code' => 'target_blocked'],
                )],
                ScanStatus::Failed,
                'target_blocked',
            );

            return;
        } catch (DnsLookupFailed) {
            $this->finish(
                $scanRun,
                [new ScanCheckResultData(
                    'target_safety',
                    ScanCheckOutcome::Error,
                    'The target could not be resolved for a safe scan.',
                    ['failure_code' => 'dns_lookup_failed'],
                )],
                ScanStatus::Failed,
                'dns_lookup_failed',
            );

            return;
        }

        sort($addresses, SORT_STRING);
        $address = $addresses[0];
        $dnsResult = $this->dnsCheck->run($hostname, $addresses);
        $endpoints = $this->endpointCheck->run($hostname, $address);
        $results = [
            $dnsResult,
            ...$endpoints->checkResults,
            $this->redirectCheck->run($hostname, $endpoints),
            $this->headersCheck->run($endpoints),
            $this->virusTotalCheck->run($hostname),
        ];

        if ($scanRun->scan_type === ScanType::WebsiteAudit) {
            array_push($results, ...$this->websiteAuditChecks->run($hostname, $address, $endpoints));
        }

        $this->finish(
            $scanRun,
            $results,
            $endpoints->hasReachableEndpoint() ? ScanStatus::Completed : ScanStatus::Failed,
            $endpoints->hasReachableEndpoint() ? null : 'target_unreachable',
        );
    }

    /**
     * @param  list<ScanCheckResultData>  $results
     */
    private function finish(ScanRun $scanRun, array $results, ScanStatus $status, ?string $failureCode): void
    {
        DB::transaction(function () use ($scanRun, $results, $status, $failureCode): void {
            foreach ($results as $result) {
                $checkResult = $scanRun->checkResults()->create([
                    'check_key' => $result->checkKey,
                    'outcome' => $result->outcome,
                    'summary' => $result->summary,
                    'evidence' => $result->evidence,
                ]);

                foreach ($result->findings as $finding) {
                    $scanRun->findings()->create([
                        'user_id' => $scanRun->user_id,
                        'rule_key' => $finding->ruleKey,
                        'title' => $finding->title,
                        'description' => $finding->description,
                        'recommended_action' => $finding->recommendedAction,
                        'severity' => $finding->severity,
                        'verification_state' => FindingVerificationState::Unverified,
                        'state' => SecurityFindingState::Open,
                        'evidence' => $finding->evidence,
                        'detected_at' => now(),
                    ]);
                }
            }

            $scanRun->forceFill([
                'status' => $status,
                'failure_code' => $failureCode,
                'finished_at' => now(),
            ])->save();
        });
    }
}
