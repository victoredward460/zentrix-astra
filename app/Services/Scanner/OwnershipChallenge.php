<?php

namespace App\Services\Scanner;

use App\Models\SecurityAsset;

final readonly class OwnershipChallenge
{
    public function __construct(
        public SecurityAsset $asset,
        public string $token,
    ) {}

    public function recordName(): string
    {
        return '_zentrix-astra-verification.'.$this->asset->hostname;
    }

    public function recordValue(): string
    {
        return 'zentrix-astra-verification='.$this->token;
    }
}
