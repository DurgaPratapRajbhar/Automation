<?php

namespace App\Jobs;

use App\Services\Campaigns\CampaignService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

// Background (queue worker): send one campaign message to one person
class SendCampaignMessage implements ShouldQueue
{
    use Queueable;

    // No automatic retry: if the job died after WhatsApp took the message, a retry would send it twice
    public int $tries = 1;

    public function __construct(
        public int $recipientId
    ) {}

    public function handle(CampaignService $campaigns): void
    {
        $campaigns->deliver($this->recipientId);
    }

    public function failed(Throwable $error): void
    {
        app(CampaignService::class)->markFailed($this->recipientId, Str::limit($error->getMessage(), 490));
    }
}
