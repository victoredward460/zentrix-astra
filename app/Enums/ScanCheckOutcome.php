<?php

namespace App\Enums;

enum ScanCheckOutcome: string
{
    case Passed = 'passed';
    case Finding = 'finding';
    case Inconclusive = 'inconclusive';
    case Error = 'error';
    case Blocked = 'blocked';
}
