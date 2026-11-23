<?php

namespace App\Services\WebsiteAuditor;

use App\Data\WebsiteAuditReportSummary;
use App\Enums\ScanStatus;
use App\Enums\SecurityFindingSeverity;
use App\Models\ScanRun;

class WebsiteAuditReportService
{
    public function summarize(ScanRun $scanRun): WebsiteAuditReportSummary
    {
        $counts = [];

        foreach (SecurityFindingSeverity::cases() as $severity) {
            $counts[$severity->value] = $scanRun->findings
                ->where('severity', $severity)
                ->count();
        }

        $incompleteCheckCount = $scanRun->checkResults
            ->whereIn('outcome', [
                \App\Enums\ScanCheckOutcome::Inconclusive,
                \App\Enums\ScanCheckOutcome::Error,
                \App\Enums\ScanCheckOutcome::Blocked,
            ])
            ->count();

        $riskCategory = match (true) {
            $scanRun->status !== ScanStatus::Completed => 'Incomplete',
            $counts[SecurityFindingSeverity::High->value] > 0 => 'High',
            $counts[SecurityFindingSeverity::Medium->value] > 0 => 'Moderate',
            $incompleteCheckCount > 0 => 'Partially assessed',
            $counts[SecurityFindingSeverity::Low->value] > 0 => 'Low',
            $counts[SecurityFindingSeverity::Informational->value] > 0 => 'Informational',
            default => 'No findings observed',
        };

        return new WebsiteAuditReportSummary(
            riskCategory: $riskCategory,
            scopeNote: 'This category summarizes only the limited observations recorded in this report. It is not a complete security rating or assurance that the website is secure.',
            severityCounts: $counts,
            incompleteCheckCount: $incompleteCheckCount,
        );
    }
}
