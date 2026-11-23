<?php

namespace App\Data;

use Carbon\CarbonImmutable;

final readonly class DashboardScan
{
    public function __construct(
        public string $module,
        public string $target,
        public string $status,
        public CarbonImmutable $startedAt,
        public ?CarbonImmutable $finishedAt,
        public int $findingCount,
    ) {}
}
