<?php

namespace App\Repositories\Contracts;

use App\Models\KnowledgeSource;
use Illuminate\Contracts\Pagination\CursorPaginator;

interface KnowledgeSourceRepositoryInterface
{
    // Search by title, newest first
    public function cursorForWorkspace(int $workspaceId, ?string $search, int $perPage): CursorPaginator;

    public function findInWorkspace(int $workspaceId, int $knowledgeSourceId): ?KnowledgeSource;

    public function create(array $data): KnowledgeSource;

    public function update(KnowledgeSource $source, array $data): KnowledgeSource;

    public function delete(KnowledgeSource $source): void;
}
