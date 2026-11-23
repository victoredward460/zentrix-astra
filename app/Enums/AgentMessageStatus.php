<?php

namespace App\Enums;

enum AgentMessageStatus: string
{
    case Completed = 'completed';
    case Failed = 'failed';
}
