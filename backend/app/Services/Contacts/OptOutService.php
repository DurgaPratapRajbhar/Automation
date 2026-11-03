<?php

namespace App\Services\Contacts;

use App\Repositories\Contracts\CustomerRepositoryInterface;

/**
 * "STOP" / "BAND": a person who writes this is never included in a campaign again.
 */
class OptOutService
{
    private const STOP_WORDS = ['stop', 'stop all', 'unsubscribe', 'band', 'band karo', 'stop karo', 'बंद', 'बंद करो'];

    public function __construct(
        private CustomerRepositoryInterface $customers
    ) {}

    public function isStopMessage(?string $text): bool
    {
        $words = preg_replace('/[^\p{L}\p{M}\p{N} ]+/u', '', mb_strtolower(trim((string) $text)));

        return in_array(trim(preg_replace('/\s+/', ' ', $words)), self::STOP_WORDS, true);
    }

    public function recordIfStop(int $workspaceId, string $phone, ?string $text): void
    {
        if (! $this->isStopMessage($text)) {
            return;
        }

        $customer = $this->customers->findByPhone($workspaceId, $phone);

        if ($customer && ! $customer->opted_out_at) {
            $this->customers->update($customer, ['opted_out_at' => now(), 'whatsapp_opt_in' => false]);
        }
    }
}
