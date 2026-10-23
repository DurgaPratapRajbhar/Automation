<?php

namespace App\Repositories\Eloquent;

use App\Models\Message;
use App\Repositories\Contracts\MessageRepositoryInterface;

class MessageRepository implements MessageRepositoryInterface
{
    public function create(array $data): Message
    {
        return Message::create($data);
    }

    public function existsByExternalId(string $externalId): bool
    {
        return Message::where('external_id', $externalId)->exists();
    }
}
