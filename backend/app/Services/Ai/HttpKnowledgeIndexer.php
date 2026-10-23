<?php

namespace App\Services\Ai;

use App\Enums\KnowledgeType;
use App\Models\KnowledgeSource;
use App\Services\Contracts\KnowledgeIndexerInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

// ai-service ko seekhne bhejta hai. Text -> /learn, file -> /learn-file (andar ka text Python nikaalta hai).
// Best-effort: service down ho to save fail nahi hoga, source 'pending' reh jata hai.
class HttpKnowledgeIndexer implements KnowledgeIndexerInterface
{
    public function index(KnowledgeSource $source): void
    {
        try {
            $base = rtrim(config('services.ai.url'), '/');

            if ($source->type === KnowledgeType::Text && $source->content) {
                Http::timeout(180)
                    ->post("{$base}/learn", [
                        'source_id' => $source->id,
                        'workspace_id' => $source->workspace_id,
                        'content' => $source->content,
                    ])
                    ->throw();

                return;
            }

            if ($source->type === KnowledgeType::File && $source->file_path) {
                Http::timeout(300)
                    ->attach('file', Storage::disk('local')->get($source->file_path), $source->file_name ?? 'file')
                    ->post("{$base}/learn-file", [
                        'source_id' => $source->id,
                        'workspace_id' => $source->workspace_id,
                    ])
                    ->throw();
            }
        } catch (\Throwable $e) {
            Log::warning('Knowledge index failed', ['source_id' => $source->id, 'error' => $e->getMessage()]);
        }
    }
}
