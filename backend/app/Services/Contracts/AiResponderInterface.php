<?php

namespace App\Services\Contracts;

// ai-service se ek sawaal ka jawaab maangne ka raasta. Asli RAG/LLM Python me.
interface AiResponderInterface
{
    // Jawaab, ya null agar AI jawaab na de paye
    public function answer(int $workspaceId, string $question): ?string;
}
