<?php

namespace App\Repositories\Contracts;

use App\Models\Message;

interface MessageRepositoryInterface
{
    public function create(array $data): Message;

    public function existsByExternalId(string $externalId): bool;
}
