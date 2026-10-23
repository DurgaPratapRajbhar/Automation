<?php

namespace App\Services\Ai;

use App\Models\KnowledgeSource;
use App\Services\Contracts\KnowledgeIndexerInterface;

// Tests me koi network call nahi
class NullKnowledgeIndexer implements KnowledgeIndexerInterface
{
    public function index(KnowledgeSource $source): void {}
}
