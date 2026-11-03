<?php

namespace App\Services\Translation;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class HttpTranslator implements TranslatorInterface
{
    public function translate(string $text, string $target): ?array
    {
        try {
            $response = Http::timeout(60)
                ->post(rtrim(config('services.ai.url'), '/').'/translate', ['text' => $text, 'target' => $target])
                ->throw();

            $translation = $response->json('translation');

            return is_string($translation) && trim($translation) !== ''
                ? ['translation' => trim($translation), 'language' => $response->json('language')]
                : null;
        } catch (Throwable $e) {
            Log::warning('Translation failed', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
