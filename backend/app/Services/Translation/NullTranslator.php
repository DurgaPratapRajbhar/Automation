<?php

namespace App\Services\Translation;

// Tests: no network call, no answer
class NullTranslator implements TranslatorInterface
{
    public function translate(string $text, string $target): ?array
    {
        return null;
    }
}
