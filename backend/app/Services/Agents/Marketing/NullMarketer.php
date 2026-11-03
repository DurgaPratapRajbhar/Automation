<?php

namespace App\Services\Agents\Marketing;

// For tests: no network, no answer
class NullMarketer implements MarketerInterface
{
    public function draft(string $business, array $products, string $brief): ?array
    {
        return null;
    }
}
