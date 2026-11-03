<?php

namespace App\Enums;

enum PromiseKind: string
{
    case Payment = 'payment';
    case Documents = 'documents';
    case Callback = 'callback';
    case Other = 'other';
}
