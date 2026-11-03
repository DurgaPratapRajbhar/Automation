<?php

namespace App\Services\Contacts;

use App\Repositories\Contracts\CustomerRepositoryInterface;

/**
 * What a person writes about marketing messages. "STOP" / "BAND": never included in a campaign again.
 * "START": they agree to get offers (and a person who had stopped can come back).
 */
class ConsentService
{
    private const STOP_WORDS = ['stop', 'stop all', 'unsubscribe', 'band', 'band karo', 'stop karo', 'बंद', 'बंद करो'];

    private const START_WORDS = ['start', 'subscribe', 'shuru', 'shuru karo', 'chalu karo', 'शुरू', 'शुरू करो'];

    public function __construct(
        private CustomerRepositoryInterface $customers
    ) {}

    public function isStopMessage(?string $text): bool
    {
        return in_array($this->clean($text), self::STOP_WORDS, true);
    }

    public function isStartMessage(?string $text): bool
    {
        return in_array($this->clean($text), self::START_WORDS, true);
    }

    /**
     * Records "STOP" or "START" if that is what the person wrote.
     *
     * @return string|null 'stopped' or 'started' (the answer to send), null for any other message
     */
    public function handle(int $workspaceId, string $phone, ?string $text, ?string $name): ?string
    {
        return match (true) {
            $this->isStopMessage($text) => $this->stop($workspaceId, $phone),
            $this->isStartMessage($text) => $this->start($workspaceId, $phone, $name),
            default => null,
        };
    }

    private function stop(int $workspaceId, string $phone): string
    {
        $customer = $this->customers->findByPhone($workspaceId, $phone);

        if ($customer && ! $customer->opted_out_at) {
            $this->customers->update($customer, ['opted_out_at' => now(), 'whatsapp_opt_in' => false]);
        }

        return 'stopped';
    }

    // A person who writes START is saved as a contact with permission (source: chat), also one who had said STOP before
    private function start(int $workspaceId, string $phone, ?string $name): string
    {
        $customer = $this->customers->findByPhone($workspaceId, $phone);

        if (! $customer) {
            $this->customers->create(['workspace_id' => $workspaceId, 'name' => $name ?: $phone, 'phone' => $phone, 'whatsapp_opt_in' => true, 'whatsapp_opt_in_source' => 'chat']);

            return 'started';
        }

        if (! $customer->whatsapp_opt_in || $customer->opted_out_at) {
            $this->customers->update($customer, ['whatsapp_opt_in' => true, 'whatsapp_opt_in_source' => 'chat', 'opted_out_at' => null]);
        }

        return 'started';
    }

    private function clean(?string $text): string
    {
        $words = preg_replace('/[^\p{L}\p{M}\p{N} ]+/u', '', mb_strtolower(trim((string) $text)));

        return trim(preg_replace('/\s+/', ' ', $words));
    }
}
