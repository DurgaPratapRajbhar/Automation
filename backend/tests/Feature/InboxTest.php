<?php

use App\Models\Channel;
use App\Models\ChannelCredential;
use App\Models\Message;
use App\Services\InboundMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
});

function inboxChannel(array $owner, string $phoneNumberId = 'PN100'): Channel
{
    $channel = Channel::create([
        'workspace_id' => $owner['workspace_id'],
        'type' => 'whatsapp',
        'provider' => 'meta',
        'name' => 'Shop WA',
        'status' => 'connected',
        'phone_number_id' => $phoneNumberId,
    ]);

    ChannelCredential::create([
        'channel_id' => $channel->id,
        'access_token' => 'test-token',
        'config' => ['phone_number_id' => $phoneNumberId],
    ]);

    return $channel;
}

// Same shape as a Meta webhook customer message
function customerWrites(string $phoneNumberId, string $text, string $from = '919876543210', string $name = 'Ramesh'): void
{
    app(InboundMessageService::class)->handle([
        'metadata' => ['phone_number_id' => $phoneNumberId],
        'contacts' => [['profile' => ['name' => $name]]],
        'messages' => [[
            'id' => 'wamid.'.uniqid(),
            'from' => $from,
            'type' => 'text',
            'text' => ['body' => $text],
        ]],
    ]);
}

test('a customer message shows in the inbox as unread, and opening the chat marks it read', function () {
    $owner = registerOwner($this);
    $headers = headersFor($owner['token'], $owner['workspace_id']);
    inboxChannel($owner);

    customerWrites('PN100', 'chawal ka rate?');
    customerWrites('PN100', '5 kg chahiye');

    $list = $this->getJson('/api/conversations', $headers)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.customer_name', 'Ramesh')
        ->assertJsonPath('data.0.unread_count', 2)
        ->assertJsonPath('data.0.last_message.body', '5 kg chahiye');

    $id = $list->json('data.0.id');

    $this->getJson("/api/conversations/{$id}", $headers)
        ->assertOk()
        ->assertJsonCount(2, 'data.messages')
        ->assertJsonPath('data.messages.0.body', 'chawal ka rate?')
        ->assertJsonPath('data.messages.0.direction', 'inbound')
        ->assertJsonPath('data.unread_count', 0);

    $this->getJson('/api/conversations', $headers)->assertJsonPath('data.0.unread_count', 0);
});

test('bot replies are marked as AI and staff replies show who sent them', function () {
    $owner = registerOwner($this);
    $headers = headersFor($owner['token'], $owner['workspace_id']);
    inboxChannel($owner);
    customerWrites('PN100', 'delivery hai?');

    $id = $this->getJson('/api/conversations', $headers)->json('data.0.id');

    // Bot reply: no sent_by_user_id
    Message::create([
        'conversation_id' => $id,
        'workspace_id' => $owner['workspace_id'],
        'direction' => 'outbound',
        'type' => 'text',
        'body' => 'Haan, free delivery.',
        'status' => 'sent',
    ]);

    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.r1']]], 200)]);

    $this->postJson("/api/conversations/{$id}/messages", ['body' => 'Aaj shaam tak bhej denge.'], $headers)
        ->assertCreated()
        ->assertJsonPath('message', 'Message sent.')
        ->assertJsonPath('data.status', 'sent')
        ->assertJsonPath('data.is_ai', false)
        ->assertJsonPath('data.sent_by', 'Owner');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/PN100/messages')
        && $request['to'] === '919876543210'
        && $request['text']['body'] === 'Aaj shaam tak bhej denge.');

    $this->getJson("/api/conversations/{$id}", $headers)
        ->assertJsonCount(3, 'data.messages')
        ->assertJsonPath('data.messages.1.is_ai', true)
        ->assertJsonPath('data.messages.2.sent_by', 'Owner');
});

test('a reply that WhatsApp rejects is kept and marked failed', function () {
    $owner = registerOwner($this);
    $headers = headersFor($owner['token'], $owner['workspace_id']);
    inboxChannel($owner);
    customerWrites('PN100', 'hello');
    $id = $this->getJson('/api/conversations', $headers)->json('data.0.id');

    Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'bad token']], 401)]);

    $this->postJson("/api/conversations/{$id}/messages", ['body' => 'Hi'], $headers)
        ->assertCreated()
        ->assertJsonPath('message', 'Message saved, but WhatsApp sending failed.')
        ->assertJsonPath('data.status', 'failed');
});

test('inbox search finds chats by customer name or phone', function () {
    $owner = registerOwner($this);
    $headers = headersFor($owner['token'], $owner['workspace_id']);
    inboxChannel($owner);
    customerWrites('PN100', 'hi', '919000000001', 'Geeta Devi');
    customerWrites('PN100', 'hi', '919000000002', 'Mohan');

    $this->getJson('/api/conversations?search=geeta', $headers)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.customer_name', 'Geeta Devi');

    $this->getJson('/api/conversations?search=0002', $headers)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.customer_name', 'Mohan');
});

test('reading needs conversations.view and replying needs messages.create', function () {
    $owner = registerOwner($this);
    inboxChannel($owner);
    customerWrites('PN100', 'hi');
    $id = $this->getJson('/api/conversations', headersFor($owner['token'], $owner['workspace_id']))->json('data.0.id');

    $viewer = memberWithPermissions($this, $owner, 'Inbox Viewer', ['conversations.view'], 'iv@example.com');
    $headers = headersFor($viewer['token'], $viewer['workspace_id']);

    $this->getJson('/api/conversations', $headers)->assertOk();
    $this->getJson("/api/conversations/{$id}", $headers)->assertOk();
    $this->postJson("/api/conversations/{$id}/messages", ['body' => 'x'], $headers)->assertForbidden();

    $nobody = memberWithPermissions($this, $owner, 'No Inbox', ['customers.view'], 'ni@example.com');
    $this->getJson('/api/conversations', headersFor($nobody['token'], $nobody['workspace_id']))->assertForbidden();
});

test('chats of another shop are invisible', function () {
    $ownerA = registerOwner($this, 'a@example.com');
    $ownerB = registerOwner($this, 'b@example.com');
    inboxChannel($ownerB, 'PN200');
    customerWrites('PN200', 'B ka customer');

    $idB = $this->getJson('/api/conversations', headersFor($ownerB['token'], $ownerB['workspace_id']))->json('data.0.id');
    $headersA = headersFor($ownerA['token'], $ownerA['workspace_id']);

    $this->getJson('/api/conversations', $headersA)->assertOk()->assertJsonCount(0, 'data');
    $this->getJson("/api/conversations/{$idB}", $headersA)->assertNotFound();
    $this->postJson("/api/conversations/{$idB}/messages", ['body' => 'x'], $headersA)->assertNotFound();
});
