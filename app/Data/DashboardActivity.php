<?php

namespace App\Data;

use Carbon\CarbonImmutable;

final readonly class DashboardActivity
{
    public function __construct(
        public string $title,
        public string $description,
        public CarbonImmutable $occurredAt,
    ) {}
}
