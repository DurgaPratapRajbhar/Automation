<?php

namespace App\Services\Ai;

use App\Services\Contracts\AiResponderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HttpAiResponder implements AiResponderInterface
{
    public function answer(int $workspaceId, string $question): ?string
    {
        try {
            $response = Http::timeout(180)
                ->post(rtrim(config('services.ai.url'), '/').'/ask', [
                    'workspace_id' => $workspaceId,
                    'question' => $question,
                ])
                ->throw();

            $answer = $response->json('answer');

            return is_string($answer) && trim($answer) !== '' ? $answer : null;
        } catch (\Throwable $e) {
            Log::warning('AI responder failed', ['workspace_id' => $workspaceId, 'error' => $e->getMessage()]);

            return null;
        }
    }
}
