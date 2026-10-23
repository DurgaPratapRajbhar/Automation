<?php

namespace App\Repositories\Eloquent;

use App\Models\Message;
use App\Repositories\Contracts\MessageRepositoryInterface;

class MessageRepository implements MessageRepositoryInterface
{
    public function find(int $id): ?Message
    {
        return Message::find($id);
    }

    public function create(array $data): Message
    {
        return Message::create($data);
    }

    public function setStatus(Message $message, string $status): void
    {
        $message->update(['status' => $status]);
    }

    public function existsByExternalId(string $externalId): bool
    {
        return Message::where('external_id', $externalId)->exists();
    }
}
