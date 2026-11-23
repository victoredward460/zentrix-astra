<?php

namespace App\Data;

use App\Enums\ScanCheckOutcome;

final readonly class ScanCheckResultData
{
    /**
     * @param array<string, bool|string|int|array|null> $evidence
     * @param list<ScanFindingData> $findings
     */
    public function __construct(
        public string $checkKey,
        public ScanCheckOutcome $outcome,
        public string $summary,
        public array $evidence = [],
        public array $findings = [],
    ) {}
}
