<?php

namespace App\Enums;

enum KhataEntryType: string
{
    // Goods given on credit: the customer owes more
    case Debit = 'debit';

    // Payment received: the customer owes less
    case Credit = 'credit';
}
