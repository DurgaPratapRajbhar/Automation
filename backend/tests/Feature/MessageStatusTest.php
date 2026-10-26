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
    config([
        'services.meta.graph_url' => 'https://graph.facebook.com',
        'services.meta.graph_version' => 'v25.0',
        'services.meta.app_secret' => 'test-secret',
    ]);
});

// Shop number PN700 with one customer chat; returns the chat id
function statusChat(array $owner): int
{
    $channel = Channel::create([
        'workspace_id' => $owner['workspace_id'],
        'type' => 'whatsapp',
        'provider' => 'meta',
        'name' => 'Shop WA',
        'status' => 'connected',
        'phone_number_id' => 'PN700',
    ]);

    ChannelCredential::create([
        'channel_id' => $channel->id,
        'access_token' => 'SHOP_TOKEN',
        'config' => ['phone_number_id' => 'PN700'],
    ]);

    app(InboundMessageService::class)->handle([
        'metadata' => ['phone_number_id' => 'PN700'],
        'contacts' => [['profile' => ['name' => 'Ramesh']]],
        'messages' => [['id' => 'wamid.in1', 'from' => '919876543210', 'type' => 'text', 'text' => ['body' => 'hi']]],
    ]);

    return (int) Message::latest('id')->value('conversation_id');
}

// Meta's status webhook, signed like the real one
function metaReports(\Tests\TestCase $test, string $wamid, string $status, array $errors = []): void
{
    $body = json_encode(['entry' => [['changes' => [['value' => [
        'metadata' => ['phone_number_id' => 'PN700'],
        'statuses' => [array_filter(['id' => $wamid, 'status' => $status, 'errors' => $errors])],
    ]]]]]]);

    $test->call('POST', '/api/webhooks/whatsapp', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'test-secret'),
    ], $body)->assertOk();
}

test('a sent reply keeps the wamid and moves to delivered, then read', function () {
    $owner = registerOwner($this);
    $chatId = statusChat($owner);
    $headers = headersFor($owner['token'], $owner['workspace_id']);
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.out1']]])]);

    $id = $this->postJson("/api/conversations/{$chatId}/messages", ['body' => 'aaj shaam tak'], $headers)
        ->assertCreated()
        ->json('data.id');

    expect(Message::find($id)->external_id)->toBe('wamid.out1');

    metaReports($this, 'wamid.out1', 'delivered');
    expect(Message::find($id)->status)->toBe('delivered');

    metaReports($this, 'wamid.out1', 'read');
    $this->getJson("/api/conversations/{$chatId}", $headers)->assertJsonPath('data.messages.1.status', 'read');
});

test('a late or repeated webhook never moves the status backwards', function () {
    $owner = registerOwner($this);
    $chatId = statusChat($owner);
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.out2']]])]);

    $id = $this->postJson("/api/conversations/{$chatId}/messages", ['body' => 'ok'], headersFor($owner['token'], $owner['workspace_id']))
        ->json('data.id');

    metaReports($this, 'wamid.out2', 'read');
    metaReports($this, 'wamid.out2', 'delivered');
    metaReports($this, 'wamid.out2', 'sent');
    metaReports($this, 'wamid.out2', 'failed');

    expect(Message::find($id)->status)->toBe('read');
});

test('a failure from Meta is shown in the chat with its reason', function () {
    $owner = registerOwner($this);
    $chatId = statusChat($owner);
    $headers = headersFor($owner['token'], $owner['workspace_id']);
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.out3']]])]);

    $this->postJson("/api/conversations/{$chatId}/messages", ['body' => 'ok'], $headers)->assertCreated();

    metaReports($this, 'wamid.out3', 'failed', [[
        'code' => 131042,
        'title' => 'Business eligibility payment issue',
        'error_data' => ['details' => 'No payment method is set up for your WhatsApp Business account.'],
    ]]);

    $this->getJson("/api/conversations/{$chatId}", $headers)
        ->assertJsonPath('data.messages.1.status', 'failed')
        ->assertJsonPath('data.messages.1.error', '(131042) No payment method is set up for your WhatsApp Business account.');
});

test('when sending itself fails the reason is saved too', function () {
    $owner = registerOwner($this);
    $chatId = statusChat($owner);
    Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid OAuth access token.']], 401)]);

    $id = $this->postJson("/api/conversations/{$chatId}/messages", ['body' => 'ok'], headersFor($owner['token'], $owner['workspace_id']))
        ->json('data.id');

    expect(Message::find($id))
        ->status->toBe('failed')
        ->error->toBe('Invalid OAuth access token.')
        ->external_id->toBeNull();
});

test('a status for a message we do not know is ignored', function () {
    $owner = registerOwner($this);
    statusChat($owner);

    metaReports($this, 'wamid.someone-else', 'delivered');

    expect(Message::where('status', 'delivered')->exists())->toBeFalse();
});
