<?php

use App\Models\Channel;
use App\Models\ChannelCredential;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Workspace;
use App\Services\Agents\AgentRouter;
use App\Services\Ai\AiAnswer;
use App\Services\AiReplyService;
use App\Services\Contracts\AiResponderInterface;
use App\Services\ConversationService;
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
        public function answer(int $workspaceId, string $question, string $replyStyle = 'auto', array $history = []): ?AiAnswer
        {
            return new AiAnswer('Haan, hum home delivery karte hain.');
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
        public function answer(int $workspaceId, string $question, string $replyStyle = 'auto', array $history = []): ?AiAnswer
        {
            return new AiAnswer('Reply text');
        }
    });

    Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'bad token']], 401)]);

    app(AiReplyService::class)->replyTo($inbound->id);

    $outbound = Message::where('conversation_id', $conversation->id)->where('direction', 'outbound')->first();
    expect($outbound)->not->toBeNull()->and($outbound->status)->toBe('failed');
});

// An AI that remembers what it was asked, and answers with the given reply
function recordingAi(string $reply = 'Theek hai.', bool $handoff = false): object
{
    $ai = new class($reply, $handoff) implements AiResponderInterface
    {
        public array $calls = [];

        public function __construct(private string $reply, private bool $handoff) {}

        public function answer(int $workspaceId, string $question, string $replyStyle = 'auto', array $history = []): ?AiAnswer
        {
            $this->calls[] = compact('question', 'replyStyle', 'history');

            return new AiAnswer($this->reply, $this->handoff);
        }
    };

    app()->instance(AiResponderInterface::class, $ai);

    return $ai;
}

function addLine(Conversation $conversation, string $direction, string $body): Message
{
    return Message::create([
        'conversation_id' => $conversation->id,
        'workspace_id' => $conversation->workspace_id,
        'direction' => $direction,
        'type' => 'text',
        'body' => $body,
        'status' => $direction === 'inbound' ? 'received' : 'sent',
    ]);
}

function fakeWhatsApp(): void
{
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]], 200)]);
}

test('the AI reads the chat before the message, oldest first, so "haan" is understood', function () {
    $owner = registerOwner($this);
    ['conversation' => $conversation] = seedInbound($owner, 'Mujhe website banwana hai');
    addLine($conversation, 'outbound', 'Kya main aapko owner se connect kar doon?');
    $haan = addLine($conversation, 'inbound', 'Haan');
    $ai = recordingAi();
    fakeWhatsApp();

    app(AiReplyService::class)->replyTo($haan->id);

    expect($ai->calls[0]['question'])->toBe('Haan')
        ->and($ai->calls[0]['history'])->toBe([
            ['from' => 'customer', 'text' => 'Mujhe website banwana hai'],
            ['from' => 'business', 'text' => 'Kya main aapko owner se connect kar doon?'],
        ]);
});

test('the chat sent to the AI is capped and never includes a later message', function () {
    config(['bot.history_messages' => 2]);
    $owner = registerOwner($this);
    ['conversation' => $conversation] = seedInbound($owner, 'pehla');
    addLine($conversation, 'outbound', 'doosra');
    addLine($conversation, 'inbound', 'teesra');
    $asked = addLine($conversation, 'inbound', 'chautha');
    addLine($conversation, 'inbound', 'baad wala');
    $ai = recordingAi();
    fakeWhatsApp();

    app(AiReplyService::class)->replyTo($asked->id);

    expect(array_column($ai->calls[0]['history'], 'text'))->toBe(['doosra', 'teesra']);
});

test('the shop reply style reaches the AI on WhatsApp too', function () {
    $owner = registerOwner($this);
    Workspace::find($owner['workspace_id'])->update(['reply_style' => 'hinglish']);
    ['inbound' => $inbound] = seedInbound($owner);
    $ai = recordingAi();
    fakeWhatsApp();

    app(AiReplyService::class)->replyTo($inbound->id);

    expect($ai->calls[0]['replyStyle'])->toBe('hinglish');
});

test('when the customer wants the owner: the reply is sent, the owner is told, and the bot goes quiet in that chat', function () {
    $owner = registerOwner($this);
    Workspace::find($owner['workspace_id'])->update(['phone' => '9000011111', 'owner_alerts' => true]);
    ['conversation' => $conversation, 'inbound' => $inbound] = seedInbound($owner, 'Haan, owner se baat karao');
    recordingAi('Owner ko bata diya hai, woh yahin reply karenge.', handoff: true);
    fakeWhatsApp();

    app(AiReplyService::class)->replyTo($inbound->id);

    expect($conversation->refresh()->botPaused())->toBeTrue();
    Http::assertSent(fn ($request) => ($request['to'] ?? null) === '919876543210'
        && $request['text']['body'] === 'Owner ko bata diya hai, woh yahin reply karenge.');
    Http::assertSent(fn ($request) => ($request['to'] ?? null) === '919000011111'
        && str_contains($request['text']['body'], 'Ramesh wants to talk to you')
        && str_contains($request['text']['body'], 'Haan, owner se baat karao'));
});

test('a normal answer does not pause the bot', function () {
    $owner = registerOwner($this);
    ['conversation' => $conversation, 'inbound' => $inbound] = seedInbound($owner);
    recordingAi('Hum 10 baje khulte hain.');
    fakeWhatsApp();

    app(AiReplyService::class)->replyTo($inbound->id);

    expect($conversation->refresh()->botPaused())->toBeFalse();
});

test('while the bot is paused no agent answers the customer, and after the pause it answers again', function () {
    $owner = registerOwner($this);
    ['conversation' => $conversation, 'inbound' => $inbound] = seedInbound($owner, 'Ok');
    $conversation->update(['bot_paused_until' => now()->addHour()]);
    $ai = recordingAi();
    fakeWhatsApp();

    app(AgentRouter::class)->replyTo($inbound->id);
    expect($ai->calls)->toBe([]);

    $this->travel(2)->hours();
    app(AgentRouter::class)->replyTo($inbound->id);
    expect($ai->calls)->toHaveCount(1);
});

test('the owner replying from the Inbox pauses the bot in that chat and the Inbox shows it', function () {
    $owner = registerOwner($this);
    ['conversation' => $conversation] = seedInbound($owner);
    fakeWhatsApp();

    app(ConversationService::class)->reply($owner['workspace_id'], $conversation->id, $owner['user_id'], 'Main dekh leta hoon.');

    expect($conversation->refresh()->botPaused())->toBeTrue()
        ->and($conversation->bot_paused_until->diffInHours(now(), true))->toBeGreaterThan(11);

    $shown = app(ConversationService::class)->show($owner['workspace_id'], $conversation->id);
    expect($shown['bot_paused_until'])->not->toBeNull();
});
