<?php

namespace App\Services\Agents\Sales;

// For tests: no network, no answer
class NullQualifier implements QualifierInterface
{
    public function qualify(array $questions, string $transcript): ?array
    {
        return null;
    }
}
