<?php

namespace App\Services\Contracts;

use App\Models\KnowledgeSource;

// Laravel se ai-service ko "is source ko seekho" bolne ka raasta.
// Asli embedding/RAG Python me hota hai; yahan sirf trigger.
interface KnowledgeIndexerInterface
{
    public function index(KnowledgeSource $source): void;
}
