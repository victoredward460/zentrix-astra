<?php

namespace App\Enums;

enum ScanStatus: string
{
    case Requested = 'requested';
    case Queued = 'queued';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
