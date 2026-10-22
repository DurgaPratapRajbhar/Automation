<?php

namespace App\Factories;

use App\Channels\Contracts\ChannelProviderInterface;
use App\Channels\Providers\MetaWhatsAppProvider;
use InvalidArgumentException;

class ChannelProviderFactory
{
   public function make(
            string $type,
            string $provider
        ): ChannelProviderInterface {

            return match ("{$provider}.{$type}") {
                
                'meta.whatsapp'
                    => app(MetaWhatsAppProvider::class),

                default => throw new InvalidArgumentException(
                    "Unsupported channel provider: {$provider}.{$type}"
                ),
            };
        }
}