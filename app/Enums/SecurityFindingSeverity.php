<?php

namespace App\Enums;

enum SecurityFindingSeverity: string
{
    case Informational = 'informational';
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
}
