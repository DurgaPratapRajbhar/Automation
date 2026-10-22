<?php

namespace App\Enums;

enum GstStatus: string
{
    case Unregistered = 'unregistered';
    case Composition = 'composition';
    case Regular = 'regular';
}
