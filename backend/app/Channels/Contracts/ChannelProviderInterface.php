<?php

namespace App\Channels\Contracts;

interface ChannelProviderInterface
{
    public function sendMessage(
        array $credentials,
        string $recipient,
        string $message
    ): array;
}