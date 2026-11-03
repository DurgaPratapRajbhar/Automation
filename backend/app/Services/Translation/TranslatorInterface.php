<?php

namespace App\Services\Translation;

// The way to ask the ai-service for a translation. The AI itself lives in Python.
interface TranslatorInterface
{
    /**
     * $target = the language's name in English ("Hindi").
     *
     * @return array{translation: string, language: string|null}|null language = ISO code of the original text; null = no answer
     */
    public function translate(string $text, string $target): ?array;
}
