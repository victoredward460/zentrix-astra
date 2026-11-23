<?php

namespace App\Policies;

use App\Enums\AssetVerificationStatus;
use App\Models\SecurityAsset;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SecurityAssetPolicy
{
    public function view(User $user, SecurityAsset $asset): Response
    {
        return $user->getKey() === $asset->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function verify(User $user, SecurityAsset $asset): Response
    {
        return $user->getKey() === $asset->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function scan(User $user, SecurityAsset $asset): Response
    {
        if ($user->getKey() !== $asset->user_id) {
            return Response::denyAsNotFound();
        }

        return $asset->verification_status === AssetVerificationStatus::Verified
            && $asset->authorization_attested_at !== null
            ? Response::allow()
            : Response::deny('Verify asset ownership and authorization before scanning.');
    }
}
