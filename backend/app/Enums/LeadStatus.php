<?php

namespace App\Enums;

// Where a new person stands with the Sales agent
enum LeadStatus: string
{
    case Qualifying = 'qualifying'; // the agent is still asking its questions
    case Hot = 'hot';               // ready to buy soon: the owner should step in
    case Warm = 'warm';
    case Cold = 'cold';

    // The score the model gives when all questions are answered
    public static function fromScore(string $score): self
    {
        return self::tryFrom($score) ?? self::Warm;
    }
}
