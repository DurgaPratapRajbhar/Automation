<?php

namespace App\Enums;

enum CampaignStatus: string
{
    case Draft = 'draft';
    case Sending = 'sending';
    case Done = 'done';
    case Cancelled = 'cancelled';
}
