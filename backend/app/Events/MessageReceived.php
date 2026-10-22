<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class MessageReceived
{
    use Dispatchable;

    public function __construct(
        public int $workspaceId,
        public int $conversationId,
        public int $messageId,
    ) {}
}