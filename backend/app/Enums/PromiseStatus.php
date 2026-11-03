<?php

namespace App\Enums;

enum PromiseStatus: string
{
    case Open = 'open';
    case Done = 'done';
    case Missed = 'missed';
}
