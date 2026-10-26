<?php

namespace App\Enums;

// Kinds of WhatsApp template we let shops create (Meta prices them differently)
enum TemplateCategory: string
{
    case Marketing = 'MARKETING';
    case Utility = 'UTILITY';
}
