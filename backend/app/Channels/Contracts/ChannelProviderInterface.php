<?php

namespace App\Channels\Contracts;

interface ChannelProviderInterface
{
    public function sendMessage(
        array $credentials,
        string $recipient,
        string $message
    ): array;

    // Pre-approved template, allowed even outside the 24-hour customer window.
    // $parameters fill {{1}}, {{2}}... of the template body, in order.
    public function sendTemplate(
        array $credentials,
        string $recipient,
        string $templateName,
        string $language,
        array $parameters
    ): array;
}
