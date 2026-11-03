<?php

namespace App\Services;

use App\Events\MessageReceived;
use App\Repositories\Contracts\ChannelRepositoryInterface;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;
use App\Services\Agents\BotMessageService;
use App\Services\Contacts\ConsentService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

class InboundMessageService
{
    public function __construct(
        private ChannelRepositoryInterface $channels,
        private ConversationRepositoryInterface $conversations,
        private MessageRepositoryInterface $messages,
        private ConsentService $consent,
        private BotMessageService $bot,
    ) {}

    /**
     * $value = entry.0.changes.0.value from Meta's webhook
     */
    public function handle(array $value): void
    {
        $incoming = $value['messages'][0] ?? null;

        // Status update (sent/delivered/read), not a new message
        if (! $incoming) {
            return;
        }

        $externalId = $incoming['id'] ?? null;

        // 1) Idempotency: Meta retries webhooks — save each message only once
        if (! $externalId || $this->messages->existsByExternalId($externalId)) {
            return;
        }

        // 2) Which workspace? phone_number_id → channel → workspace
        $phoneNumberId = $value['metadata']['phone_number_id'] ?? '';
        $channel = $this->channels->findConnectedByPhoneNumberId($phoneNumberId);

        if (! $channel) {
            logger()->warning('WhatsApp webhook: no connected channel for number', [
                'phone_number_id' => $phoneNumberId,
            ]);

            return;
        }

        try {
            $message = DB::transaction(function () use ($channel, $value, $incoming, $externalId) {

                // 3) Find or create the chat with this customer
                $conversation = $this->conversations->findOrCreateForCustomer(
                    $channel->workspace_id,
                    $channel->id,
                    $incoming['from'],
                    $value['contacts'][0]['profile']['name'] ?? null,
                );

                // 4) Save the message bubble
                $message = $this->messages->create([
                    'conversation_id' => $conversation->id,
                    'workspace_id' => $channel->workspace_id,
                    'direction' => 'inbound',
                    'external_id' => $externalId,
                    'type' => $incoming['type'] ?? 'text',
                    'body' => $incoming['text']['body'] ?? null,
                    'payload' => $incoming,
                    'status' => 'received',
                ]);

                $this->conversations->touchLastMessage($conversation);
                $this->conversations->incrementUnread($conversation);

                return $message;
            });
        } catch (UniqueConstraintViolationException) {
            // Two copies of the same webhook arrived at the same moment — the other one saved it
            return;
        }

        // 5) "STOP" / "BAND": no more campaign messages to this person; "START": they agree to get offers. Both get a fixed answer.
        $answer = $this->consent->handle($message->workspace_id, $incoming['from'], $incoming['text']['body'] ?? null, $value['contacts'][0]['profile']['name'] ?? null);

        if ($answer) {
            // A fixed confirmation (WhatsApp expects one); the AI is not asked about it
            $this->bot->send($this->conversations->find($message->conversation_id), __("consent.{$answer}"));

            return;
        }

        // 6) A flood from one chat is saved but not answered
        $limit = "agents:{$message->conversation_id}";

        if (RateLimiter::tooManyAttempts($limit, config('operations.chat_messages_per_minute'))) {
            return;
        }

        RateLimiter::hit($limit, 60);

        // 7) Ring the bell — AI reply, notifications etc. will listen (next steps)
        MessageReceived::dispatch(
            $message->workspace_id,
            $message->conversation_id,
            $message->id,
        );
    }
}
