<?php

namespace App\Services\Agents\Sales;

// The way to ask the ai-service for the Sales agent's next message. The AI itself lives in Python.
interface QualifierInterface
{
    /**
     * @param  string[]  $questions
     * @param  string  $transcript  the chat so far, one line per message
     * @return array{answers: array<int, string|null>, reply: string, done: bool, score: string, summary: string}|null null = no answer
     */
    public function qualify(array $questions, string $transcript): ?array;
}
