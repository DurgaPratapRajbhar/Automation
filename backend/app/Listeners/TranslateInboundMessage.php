<?php

namespace App\Listeners;

use App\Events\MessageReceived;
use App\Services\Translation\TranslationService;
use Illuminate\Contracts\Queue\ShouldQueue;

// Background (queue worker): give the customer's message its Inbox-language version
class TranslateInboundMessage implements ShouldQueue
{
    public int $tries = 2;

    public function __construct(
        private TranslationService $translation
    ) {}

    public function handle(MessageReceived $event): void
    {
        $this->translation->translateInbound($event->messageId);
    }
}
