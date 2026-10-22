<?php

namespace App\Listeners;

use App\Events\MessageReceived;
use Illuminate\Contracts\Queue\ShouldQueue;

class GenerateAiReply implements ShouldQueue   // ← this one line = run in background
{
    // If it fails (e.g. AI API down), retry up to 3 times
    public int $tries = 3;

    // Wait 10s before 2nd try, 60s before 3rd
    public array $backoff = [10, 60];

    public function handle(MessageReceived $event): void
    {
        logger()->info('Queue: AI reply job started', [
            'message_id' => $event->messageId,
        ]);

        sleep(5);   // pretend the AI is thinking (real AI comes in the next step)

        logger()->info('Queue: AI reply job finished', [
            'message_id' => $event->messageId,
        ]);
    }
}