<?php

namespace App\Services\Campaigns;

use App\Models\Channel;
use App\Services\Meta\MetaGraph;
use Throwable;

/**
 * How many new people WhatsApp lets a number message in one day (a new number starts low and is raised as it is used well).
 * Only used to warn before a big campaign; a number that cannot be checked is simply not warned about.
 */
class SendingLimit
{
    private const PER_DAY = ['TIER_50' => 50, 'TIER_250' => 250, 'TIER_1K' => 1000, 'TIER_10K' => 10000, 'TIER_100K' => 100000];

    public function __construct(
        private MetaGraph $graph
    ) {}

    /** @return array{tier: string, per_day: int|null}|null per_day null = no limit; null = Meta could not be asked */
    public function forChannel(Channel $channel): ?array
    {
        $token = $channel->credential?->access_token;

        if (! $token || ! $channel->phone_number_id) {
            return null;
        }

        try {
            $tier = $this->graph->get($channel->phone_number_id, $token, ['fields' => 'messaging_limit_tier'])->json('messaging_limit_tier');
        } catch (Throwable) {
            return null;
        }

        return $tier ? ['tier' => $tier, 'per_day' => self::PER_DAY[$tier] ?? null] : null;
    }
}
