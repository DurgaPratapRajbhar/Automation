<?php

namespace App\Listeners;

use App\Events\MessageReceived;
use App\Services\Agents\FinanceAgent;
use Illuminate\Contracts\Queue\ShouldQueue;

// In the background: the Finance agent notes any dated promise in the customer's message
class TrackPromises implements ShouldQueue
{
    public int $tries = 3;

    public array $backoff = [10, 60];

    public function __construct(private FinanceAgent $finance) {}

    public function handle(MessageReceived $event): void
    {
        $this->finance->observe($event->messageId);
    }
}
