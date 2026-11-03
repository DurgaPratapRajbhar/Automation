<?php

namespace App\Services\Agents;

use App\Models\Conversation;
use App\Models\Message;

// An agent answers the customer for one kind of job (support, sales, ...). One agent = one file.
interface AgentInterface
{
    // Is this message this agent's job right now?
    public function handles(Conversation $conversation, Message $message): bool;

    // Answer the customer's message
    public function reply(Conversation $conversation, Message $message): void;
}
