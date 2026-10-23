<?php

use App\Models\Channel;
use App\Models\ChannelCredential;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);

    // Asli Meta API par call nahi jayegi
    Http::fake([
        'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.TEST']]]),
    ]);
});

function createWhatsAppChannel(int $workspaceId, string $status = 'connected', ?string $token = 'test-token'): Channel
{
    $channel = Channel::create([
        'workspace_id' => $workspaceId,
        'type' => 'whatsapp',
        'provider' => 'meta',
        'name' => 'Test WhatsApp',
        'status' => $status,
    ]);

    ChannelCredential::create([
        'channel_id' => $channel->id,
        'access_token' => $token,
        'config' => ['phone_number_id' => '111222333'],
    ]);

    return $channel;
}

function sendPayload(int $channelId): array
{
    return [
        'channel_id' => $channelId,
        'recipient' => '919999999999',
        'message' => 'Hello from test',
    ];
}

test('owner can send message through own workspace channel', function () {
    $owner = registerOwner($this);
    $channel = createWhatsAppChannel($owner['workspace_id']);

    $this->postJson('/api/channels/send-message', sendPayload($channel->id), headersFor($owner['token'], $owner['workspace_id']))
        ->assertOk()
        ->assertJsonPath('data.messages.0.id', 'wamid.TEST');

    Http::assertSent(fn (HttpRequest $request) => str_contains($request->url(), '/111222333/messages')
        && $request->hasHeader('Authorization', 'Bearer test-token')
        && $request['to'] === '919999999999'
    );
});

test('channel of another workspace is not found', function () {
    $ownerA = registerOwner($this, 'a@example.com');
    $ownerB = registerOwner($this, 'b@example.com');
    $channelOfB = createWhatsAppChannel($ownerB['workspace_id']);

    $this->postJson('/api/channels/send-message', sendPayload($channelOfB->id), headersFor($ownerA['token'], $ownerA['workspace_id']))
        ->assertNotFound()
        ->assertJsonPath('message', 'Channel not found.');

    Http::assertNothingSent();
});

test('disconnected channel is not found', function () {
    $owner = registerOwner($this);
    $channel = createWhatsAppChannel($owner['workspace_id'], status: 'disconnected');

    $this->postJson('/api/channels/send-message', sendPayload($channel->id), headersFor($owner['token'], $owner['workspace_id']))
        ->assertNotFound();

    Http::assertNothingSent();
});

test('channel without access token returns 422', function () {
    $owner = registerOwner($this);
    $channel = createWhatsAppChannel($owner['workspace_id'], token: null);

    $this->postJson('/api/channels/send-message', sendPayload($channel->id), headersFor($owner['token'], $owner['workspace_id']))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Channel credentials are missing.');

    Http::assertNothingSent();
});
