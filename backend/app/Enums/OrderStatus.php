<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Draft = 'draft';         // the assistant wrote it from the chat; the owner has not confirmed
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
}
