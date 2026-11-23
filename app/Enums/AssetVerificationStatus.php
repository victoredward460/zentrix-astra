<?php

namespace App\Enums;

enum AssetVerificationStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
}
