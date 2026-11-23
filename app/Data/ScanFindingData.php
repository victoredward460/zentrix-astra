<?php

namespace App\Data;

use App\Enums\SecurityFindingSeverity;

final readonly class ScanFindingData
{
    /**
     * @param array<string, bool|string|int|null> $evidence
     */
    public function __construct(
        public string $ruleKey,
        public string $title,
        public string $description,
        public SecurityFindingSeverity $severity,
        public array $evidence,
        public ?string $recommendedAction = null,
    ) {}
}
