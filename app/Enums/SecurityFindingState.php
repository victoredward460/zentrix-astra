<?php

namespace App\Enums;

enum SecurityFindingState: string
{
    case Open = 'open';
    case Resolved = 'resolved';
}
