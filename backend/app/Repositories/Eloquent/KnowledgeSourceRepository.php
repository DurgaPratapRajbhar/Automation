<?php

namespace App\Repositories\Eloquent;

use App\Models\KnowledgeSource;
use App\Repositories\Contracts\KnowledgeSourceRepositoryInterface;
use Illuminate\Contracts\Pagination\CursorPaginator;

class KnowledgeSourceRepository implements KnowledgeSourceRepositoryInterface
{
    public function cursorForWorkspace(int $workspaceId, ?string $search, int $perPage): CursorPaginator
    {
        return KnowledgeSource::query()
            ->where('workspace_id', $workspaceId)
            ->when($search !== null && $search !== '', function ($query) use ($search) {
                // LOWER() makes the search case-insensitive on Postgres too
                $query->whereRaw('LOWER(title) LIKE ?', ['%'.mb_strtolower($search).'%']);
            })
            ->orderByDesc('id')
            ->cursorPaginate($perPage);
    }

    public function findInWorkspace(int $workspaceId, int $knowledgeSourceId): ?KnowledgeSource
    {
        return KnowledgeSource::where('workspace_id', $workspaceId)->find($knowledgeSourceId);
    }

    public function create(array $data): KnowledgeSource
    {
        return KnowledgeSource::create($data);
    }

    public function update(KnowledgeSource $source, array $data): KnowledgeSource
    {
        if ($data !== []) {
            $source->update($data);
        }

        return $source->refresh();
    }

    public function delete(KnowledgeSource $source): void
    {
        $source->delete();
    }
}
