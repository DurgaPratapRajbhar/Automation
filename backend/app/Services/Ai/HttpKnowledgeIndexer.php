<?php

namespace App\Services\Ai;

use App\Enums\KnowledgeType;
use App\Models\KnowledgeSource;
use App\Services\Contracts\KnowledgeIndexerInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// ai-service ke /learn ko call karta hai. Best-effort: service down ho to save fail nahi hoga,
// source 'pending' reh jata hai aur dobara seekhaya ja sakta hai.
class HttpKnowledgeIndexer implements KnowledgeIndexerInterface
{
    public function index(KnowledgeSource $source): void
    {
        // Abhi sirf text; file se text nikalna aage ke step me
        if ($source->type !== KnowledgeType::Text || ! $source->content) {
            return;
        }

        try {
            Http::timeout(120)
                ->post(rtrim(config('services.ai.url'), '/').'/learn', [
                    'source_id' => $source->id,
                    'workspace_id' => $source->workspace_id,
                    'content' => $source->content,
                ])
                ->throw();
        } catch (\Throwable $e) {
            Log::warning('Knowledge index failed', ['source_id' => $source->id, 'error' => $e->getMessage()]);
        }
    }
}
