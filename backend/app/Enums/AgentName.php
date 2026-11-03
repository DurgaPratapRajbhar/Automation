<?php

namespace App\Enums;

enum AgentName: string
{
    case Support = 'support';
    case Sales = 'sales';
    case Finance = 'finance';
    case Operations = 'operations';
}
