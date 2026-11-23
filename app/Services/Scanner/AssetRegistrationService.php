<?php

namespace App\Services\Scanner;

use App\Models\SecurityAsset;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AssetRegistrationService
{
    public function __construct(
        private readonly HostnamePolicy $hostnamePolicy,
        private readonly DomainOwnershipService $ownershipService,
    ) {}

    public function register(User $user, string $hostname): OwnershipChallenge
    {
        $normalizedHostname = $this->hostnamePolicy->normalize($hostname);

        return DB::transaction(function () use ($user, $normalizedHostname): OwnershipChallenge {
            $asset = $user->securityAssets()->create([
                'hostname' => $normalizedHostname,
                'authorization_attested_at' => now(),
            ]);

            return $this->ownershipService->issue($asset);
        });
    }
}
