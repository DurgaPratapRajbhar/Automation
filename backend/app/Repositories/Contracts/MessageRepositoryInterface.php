<?php

namespace App\Repositories\Contracts;

use App\Models\Message;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

interface MessageRepositoryInterface
{
    public function find(int $id): ?Message;

    // Chat screen: last $limit messages, oldest first
    public function forConversation(int $conversationId, int $limit): Collection;

    // When the customer last wrote (starts WhatsApp's 24-hour reply window)
    public function lastInboundAt(int $conversationId): ?CarbonInterface;

    public function create(array $data): Message;

    public function setStatus(Message $message, string $status): void;

    public function markFailed(Message $message, string $error): void;

    // Meta's wamid of a message we sent
    public function setExternalId(Message $message, string $externalId): void;

    public function existsByExternalId(string $externalId): bool;

    public function findByExternalId(string $externalId): ?Message;
}
