<?php

namespace App\Data;

final readonly class WebsiteAuditReportSummary
{
    /**
     * @param array<string, int> $severityCounts
     */
    public function __construct(
        public string $riskCategory,
        public string $scopeNote,
        public array $severityCounts,
        public int $incompleteCheckCount,
    ) {}
}
