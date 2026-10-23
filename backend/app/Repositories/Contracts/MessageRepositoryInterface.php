<?php

namespace App\Repositories\Contracts;

use App\Models\Message;

interface MessageRepositoryInterface
{
    public function find(int $id): ?Message;

    public function create(array $data): Message;

    public function setStatus(Message $message, string $status): void;

    public function existsByExternalId(string $externalId): bool;
}
