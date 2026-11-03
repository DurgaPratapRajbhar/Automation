<?php

namespace App\Enums;

enum PromiseSource: string
{
    case Agent = 'agent';   // found by the Finance agent in a chat
    case Manual = 'manual'; // added by the owner
}
