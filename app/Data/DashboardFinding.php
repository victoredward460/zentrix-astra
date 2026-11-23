<?php

namespace App\Data;

use Carbon\CarbonImmutable;

final readonly class DashboardFinding
{
    public function __construct(
        public string $title,
        public string $severity,
        public string $verificationState,
        public CarbonImmutable $recordedAt,
    ) {}
}
