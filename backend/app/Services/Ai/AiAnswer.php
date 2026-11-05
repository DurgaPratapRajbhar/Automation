<?php

namespace App\Services\Ai;

// The AI's reply to a customer. handoff = the customer should now talk to the owner (the bot goes quiet in that chat).
final readonly class AiAnswer
{
    public function __construct(
        public string $text,
        public bool $handoff = false,
    ) {}
}
