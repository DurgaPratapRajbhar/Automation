<?php

namespace App\Factories;

use App\Channels\Contracts\ChannelProviderInterface;
use App\Channels\Providers\MetaWhatsAppProvider;
use InvalidArgumentException;

class ChannelProviderFactory
{
    // "provider.type" => strategy class. Naya channel = yahan ek line.
    private const PROVIDERS = [
        'meta.whatsapp' => MetaWhatsAppProvider::class,
    ];

    public function make(string $type, string $provider): ChannelProviderInterface
    {
        $class = self::PROVIDERS["{$provider}.{$type}"] ?? null;

        if (! $class) {
            throw new InvalidArgumentException(
                "Unsupported channel provider: {$provider}.{$type}"
            );
        }

        return app($class);
    }

    public function supports(string $type, string $provider): bool
    {
        return isset(self::PROVIDERS["{$provider}.{$type}"]);
    }
}
