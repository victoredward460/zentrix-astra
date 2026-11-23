<?php

namespace App\Data;

final readonly class DashboardMetrics
{
    public function __construct(
        public int $completedScans,
        public int $activeAssessments,
        public int $openFindings,
        public int $fileChecks,
    ) {}
}
