<?php

namespace App\Enums;

enum FindingVerificationState: string
{
    case Unverified = 'unverified';
    case Suspicious = 'suspicious';
    case Confirmed = 'confirmed';
}
