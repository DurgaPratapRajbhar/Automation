<?php

namespace App\Services\Ai;

use App\Services\Contracts\AiResponderInterface;

// Tests me koi network call nahi
class NullAiResponder implements AiResponderInterface
{
    public function answer(int $workspaceId, string $question): ?string
    {
        return null;
    }
}
