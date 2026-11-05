<?php

namespace App\Services\Contracts;

use App\Services\Ai\AiAnswer;

// ai-service se ek sawaal ka jawaab maangne ka raasta. Asli RAG/LLM Python me.
interface AiResponderInterface
{
    /**
     * Jawaab, ya null agar AI jawaab na de paye.
     *
     * @param  array<int, array{from: string, text: string}>  $history  the chat before the question, oldest first
     */
    public function answer(int $workspaceId, string $question, string $replyStyle = 'auto', array $history = []): ?AiAnswer;
}
