<?php

namespace App\Enums;

// AI ne is jaankari ko padha ya nahi (processing aage ke step me)
enum KnowledgeStatus: string
{
    case Pending = 'pending';
    case Ready = 'ready';
    case Failed = 'failed';
}
