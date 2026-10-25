<?php

namespace App\Repositories\Contracts;

use App\Models\Message;
use Illuminate\Support\Collection;

interface MessageRepositoryInterface
{
    public function find(int $id): ?Message;

    // Chat screen: last $limit messages, oldest first
    public function forConversation(int $conversationId, int $limit): Collection;

    public function create(array $data): Message;

    public function setStatus(Message $message, string $status): void;

    public function existsByExternalId(string $externalId): bool;
}
