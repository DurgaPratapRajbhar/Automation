<?php

namespace App\Enums;

enum ActionStatus: string
{
    case Proposed = 'proposed';   // an agent suggests it; the owner has not decided
    case Rejected = 'rejected';
    case Executed = 'executed';   // done and checked
    case Escalated = 'escalated'; // approved but it failed or could not be checked: a person must look
}
