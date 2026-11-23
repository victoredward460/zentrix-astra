<?php

namespace App\Enums;

enum FileCheckStatus: string
{
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
