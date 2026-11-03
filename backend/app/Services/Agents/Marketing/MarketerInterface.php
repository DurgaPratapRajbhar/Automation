<?php

namespace App\Services\Agents\Marketing;

// The way to ask the ai-service to write an offer message. The AI itself lives in Python.
interface MarketerInterface
{
    /**
     * @param  array<int, array{name: string, price: string, unit: string|null}>  $products
     * @return array{name: string, language: string, body: string}|null null = no answer
     */
    public function draft(string $business, array $products, string $brief): ?array;
}
