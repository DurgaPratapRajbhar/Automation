<?php

namespace App\Services;

use App\Enums\MessageStatus;
use App\Models\Message;
use App\Repositories\Contracts\MessageRepositoryInterface;
use Closure;
use Illuminate\Support\Str;
use Throwable;

// Inbox me save hua outbound message WhatsApp se bhejo, aur Meta ka message id (wamid) yaad rakho:
// baad me delivered / read / failed ke webhook isi id se is message ko dhoondhte hain
class OutboundMessageService
{
    public function __construct(
        private MessageRepositoryInterface $messages,
    ) {}

    /**
     * $send = the actual provider call, returning the provider's response
     */
    public function deliver(Message $message, Closure $send): void
    {
        try {
            $response = $send();
        } catch (Throwable $e) {
            $this->messages->markFailed($message, Str::limit($e->getMessage(), 490));
            report($e);

            return;
        }

        // Cloud API reply: {"messages": [{"id": "wamid.xxx"}]}
        $externalId = $response['messages'][0]['id'] ?? null;

        if ($externalId) {
            $this->messages->setExternalId($message, $externalId);
        }
    }

    /**
     * $value = entry.0.changes.0.value from Meta's webhook; "statuses" = what happened to messages we sent
     */
    public function applyStatuses(array $value): void
    {
        foreach ($value['statuses'] ?? [] as $update) {
            $next = MessageStatus::tryFrom($update['status'] ?? '');
            $message = isset($update['id']) ? $this->messages->findByExternalId($update['id']) : null;

            if (! $next || ! $message || ! MessageStatus::from($message->status)->canMoveTo($next)) {
                continue;
            }

            $next === MessageStatus::Failed
                ? $this->messages->markFailed($message, $this->reason($update['errors'][0] ?? []))
                : $this->messages->setStatus($message, $next->value);
        }
    }

    // Meta's most useful explanation first: details > message > title
    private function reason(array $error): string
    {
        $text = $error['error_data']['details'] ?? $error['message'] ?? $error['title'] ?? __('conversations.delivery_failed');

        return Str::limit(isset($error['code']) ? "({$error['code']}) {$text}" : $text, 490);
    }
}
