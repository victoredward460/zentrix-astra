<?php

namespace App\Data;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final readonly class DashboardSnapshot
{
    /**
     * @param  Collection<int, DashboardFinding>  $recentFindings
     * @param  Collection<int, DashboardScan>  $scanHistory
     * @param  Collection<int, DashboardActivity>  $activityTimeline
     */
    public function __construct(
        public ?CarbonImmutable $accountCreatedAt,
        public ?DashboardMetrics $metrics,
        public Collection $recentFindings,
        public Collection $scanHistory,
        public Collection $activityTimeline,
    ) {}
}
