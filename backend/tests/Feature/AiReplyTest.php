<?php

use App\Models\Channel;
use App\Models\ChannelCredential;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\AiReplyService;
use App\Services\Contracts\AiResponderInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function seedInbound(array $owner, string $body = 'kya aap delivery karte ho?'): array
{
    $channel = Channel::create([
        'workspace_id' => $owner['workspace_id'],
        'type' => 'whatsapp',
        'provider' => 'meta',
        'name' => 'Shop WA',
        'status' => 'connected',
        'phone_number_id' => 'PN123',
    ]);

    ChannelCredential::create([
        'channel_id' => $channel->id,
        'access_token' => 'test-token',
        'config' => ['phone_number_id' => 'PN123'],
    ]);

    $conversation = Conversation::create([
        'workspace_id' => $owner['workspace_id'],
        'channel_id' => $channel->id,
        'customer_phone' => '919876543210',
        'customer_name' => 'Ramesh',
        'status' => 'open',
    ]);

    $inbound = Message::create([
        'conversation_id' => $conversation->id,
        'workspace_id' => $owner['workspace_id'],
        'direction' => 'inbound',
        'type' => 'text',
        'body' => $body,
        'status' => 'received',
    ]);

    return ['conversation' => $conversation, 'inbound' => $inbound];
}

test('AI replies to a customer message, saves it to the inbox and sends it on WhatsApp', function () {
    $owner = registerOwner($this);
    ['conversation' => $conversation, 'inbound' => $inbound] = seedInbound($owner);

    // ai-service ka jawaab fake karo
    $this->app->bind(AiResponderInterface::class, fn () => new class implements AiResponderInterface
    {
        public function answer(int $workspaceId, string $question): ?string
        {
            return 'Haan, hum home delivery karte hain.';
        }
    });

    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]], 200)]);

    app(AiReplyService::class)->replyTo($inbound->id);

    $outbound = Message::where('conversation_id', $conversation->id)->where('direction', 'outbound')->first();

    expect($outbound)->not->toBeNull()
        ->and($outbound->body)->toBe('Haan, hum home delivery karte hain.')
        ->and($outbound->status)->toBe('sent')
        ->and($outbound->sent_by_user_id)->toBeNull();

    Http::assertSent(fn ($request) => str_contains($request->url(), '/PN123/messages')
        && $request['text']['body'] === 'Haan, hum home delivery karte hain.');
});

test('no reply is sent when the AI returns nothing', function () {
    $owner = registerOwner($this);
    ['conversation' => $conversation, 'inbound' => $inbound] = seedInbound($owner);

    // Default testing binding = NullAiResponder (null jawaab)
    Http::fake();

    app(AiReplyService::class)->replyTo($inbound->id);

    expect(Message::where('conversation_id', $conversation->id)->where('direction', 'outbound')->exists())->toBeFalse();
    Http::assertNothingSent();
});

test('outbound is marked failed when WhatsApp sending fails', function () {
    $owner = registerOwner($this);
    ['conversation' => $conversation, 'inbound' => $inbound] = seedInbound($owner);

    $this->app->bind(AiResponderInterface::class, fn () => new class implements AiResponderInterface
    {
        public function answer(int $workspaceId, string $question): ?string
        {
            return 'Reply text';
        }
    });

    Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'bad token']], 401)]);

    app(AiReplyService::class)->replyTo($inbound->id);

    $outbound = Message::where('conversation_id', $conversation->id)->where('direction', 'outbound')->first();
    expect($outbound)->not->toBeNull()->and($outbound->status)->toBe('failed');
});
