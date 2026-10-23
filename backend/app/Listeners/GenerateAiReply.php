<?php

namespace App\Listeners;

use App\Events\MessageReceived;
use App\Services\AiReplyService;
use Illuminate\Contracts\Queue\ShouldQueue;

// Background me chalta hai (queue worker): AI reply banao aur bhejo
class GenerateAiReply implements ShouldQueue
{
    // AI/WhatsApp down ho to retry
    public int $tries = 3;

    public array $backoff = [10, 60];

    public function __construct(
        private AiReplyService $aiReplyService
    ) {}

    public function handle(MessageReceived $event): void
    {
        $this->aiReplyService->replyTo($event->messageId);
    }
}
