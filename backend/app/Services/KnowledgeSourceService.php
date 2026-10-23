<?php

namespace App\Services;

use App\Enums\KnowledgeStatus;
use App\Enums\KnowledgeType;
use App\Exceptions\BusinessRuleException;
use App\Models\KnowledgeSource;
use App\Repositories\Contracts\KnowledgeSourceRepositoryInterface;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

// Shop ki jaankari (text/file) store karta hai. Content badalne par status pending,
// taaki AI dobara padhe (embedding aage ke step me).
class KnowledgeSourceService
{
    // Uploaded files is private disk par rakhte hain
    private const DISK = 'local';

    public function __construct(
        private KnowledgeSourceRepositoryInterface $knowledgeSourceRepository
    ) {}

    public function list(int $workspaceId, ?string $search, int $perPage): CursorPaginator
    {
        $perPage = max(1, min($perPage, config('pagination.max_per_page')));

        return $this->knowledgeSourceRepository
            ->cursorForWorkspace($workspaceId, $search, $perPage)
            ->through(fn (KnowledgeSource $source) => $this->present($source));
    }

    public function getSource(int $workspaceId, int $knowledgeSourceId): array
    {
        return $this->present($this->findOrFail($workspaceId, $knowledgeSourceId));
    }

    public function create(int $workspaceId, array $data, ?UploadedFile $file): array
    {
        $type = KnowledgeType::from($data['type']);

        $payload = [
            'workspace_id' => $workspaceId,
            'type' => $type->value,
            'title' => $data['title'],
            'status' => KnowledgeStatus::Pending->value,
        ];

        if ($type === KnowledgeType::Text) {
            $payload['content'] = $data['content'];
        } else {
            $payload = [...$payload, ...$this->storeFile($workspaceId, $file)];
        }

        return $this->present($this->knowledgeSourceRepository->create($payload));
    }

    // Update sirf title/content badalta hai. File badalni ho to delete karke dobara add karo.
    public function update(int $workspaceId, int $knowledgeSourceId, array $data): array
    {
        $source = $this->findOrFail($workspaceId, $knowledgeSourceId);

        if (array_key_exists('content', $data) && $source->type !== KnowledgeType::Text) {
            throw new BusinessRuleException(__('knowledge.content_text_only'));
        }

        // Content badla to AI ko dobara padhna hoga
        if (array_key_exists('content', $data) && $data['content'] !== $source->content) {
            $data['status'] = KnowledgeStatus::Pending->value;
        }

        return $this->present($this->knowledgeSourceRepository->update($source, $data));
    }

    public function delete(int $workspaceId, int $knowledgeSourceId): void
    {
        $source = $this->findOrFail($workspaceId, $knowledgeSourceId);

        if ($source->file_path) {
            Storage::disk(self::DISK)->delete($source->file_path);
        }

        $this->knowledgeSourceRepository->delete($source);
    }

    private function storeFile(int $workspaceId, UploadedFile $file): array
    {
        $path = $file->store("knowledge/{$workspaceId}", self::DISK);

        return [
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
        ];
    }

    private function findOrFail(int $workspaceId, int $knowledgeSourceId): KnowledgeSource
    {
        $source = $this->knowledgeSourceRepository->findInWorkspace($workspaceId, $knowledgeSourceId);

        if (! $source) {
            throw new BusinessRuleException(__('knowledge.not_found'), 404);
        }

        return $source;
    }

    private function present(KnowledgeSource $source): array
    {
        return [
            'id' => $source->id,
            'type' => $source->type->value,
            'title' => $source->title,
            'content' => $source->content,
            'file_name' => $source->file_name,
            'size_bytes' => $source->size_bytes,
            'status' => $source->status->value,
            'created_at' => $source->created_at,
            'updated_at' => $source->updated_at,
        ];
    }
}
