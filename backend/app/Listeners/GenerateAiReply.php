<?php

namespace App\Listeners;

use App\Events\MessageReceived;
use App\Services\Agents\AgentRouter;
use Illuminate\Contracts\Queue\ShouldQueue;

// Background me chalta hai (queue worker): the right agent answers the customer
class GenerateAiReply implements ShouldQueue
{
    // AI/WhatsApp down ho to retry
    public int $tries = 3;

    public array $backoff = [10, 60];

    public function __construct(
        private AgentRouter $agents
    ) {}

    public function handle(MessageReceived $event): void
    {
        $this->agents->replyTo($event->messageId);
    }
}
