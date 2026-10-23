<?php

use App\Models\ChannelCredential;
use App\Models\Permission;
use App\Services\RoutePermissionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
});

function whatsappChannelPayload(string $phoneNumberId = '1211076188745310'): array
{
    return [
        'type' => 'whatsapp',
        'provider' => 'meta',
        'name' => 'Shop WhatsApp',
        'credentials' => [
            'access_token' => 'EAATESTTOKEN',
            'phone_number_id' => $phoneNumberId,
            'waba_id' => '1782689119764711',
        ],
    ];
}

test('permissions exist right after migrate (no seeder needed)', function () {
    expect(Permission::count())->toBe(count(app(RoutePermissionResolver::class)->all()));
});

test('owner can create, list, update and delete a channel', function () {
    $owner = registerOwner($this);
    $headers = headersFor($owner['token'], $owner['workspace_id']);

    $created = $this->postJson('/api/channels', whatsappChannelPayload(), $headers)
        ->assertCreated()
        ->assertJsonPath('data.status', 'connected')
        ->assertJsonPath('data.phone_number_id', '1211076188745310')
        ->assertJsonPath('data.has_access_token', true)
        ->assertJsonMissingPath('data.access_token');

    $channelId = $created->json('data.id');

    // Token DB me encrypted hai, plain text nahi
    $raw = ChannelCredential::where('channel_id', $channelId)->toBase()->value('access_token');
    expect($raw)->not->toBe('EAATESTTOKEN');

    $this->getJson('/api/channels', $headers)
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->putJson("/api/channels/{$channelId}", [
        'name' => 'Renamed',
        'status' => 'disconnected',
        'credentials' => ['access_token' => 'EAANEWTOKEN'],
    ], $headers)
        ->assertOk()
        ->assertJsonPath('data.name', 'Renamed')
        ->assertJsonPath('data.status', 'disconnected')
        ->assertJsonPath('data.phone_number_id', '1211076188745310');

    expect(ChannelCredential::where('channel_id', $channelId)->first()->access_token)->toBe('EAANEWTOKEN');

    $this->deleteJson("/api/channels/{$channelId}", [], $headers)->assertOk();
    $this->getJson("/api/channels/{$channelId}", $headers)->assertNotFound();
    expect(ChannelCredential::count())->toBe(0);
});

test('unsupported channel type is rejected', function () {
    $owner = registerOwner($this);

    $payload = whatsappChannelPayload();
    $payload['provider'] = 'twilio';

    $this->postJson('/api/channels', $payload, headersFor($owner['token'], $owner['workspace_id']))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Unsupported channel: twilio.whatsapp');
});

test('same whatsapp number cannot be connected twice anywhere', function () {
    $ownerA = registerOwner($this, 'a@example.com');
    $ownerB = registerOwner($this, 'b@example.com');

    $this->postJson('/api/channels', whatsappChannelPayload(), headersFor($ownerA['token'], $ownerA['workspace_id']))
        ->assertCreated();

    $this->postJson('/api/channels', whatsappChannelPayload(), headersFor($ownerB['token'], $ownerB['workspace_id']))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'This WhatsApp number is already connected to a channel.');
});

test('channel permissions are enforced', function () {
    $owner = registerOwner($this);
    $ownerHeaders = headersFor($owner['token'], $owner['workspace_id']);

    $channelId = $this->postJson('/api/channels', whatsappChannelPayload(), $ownerHeaders)->json('data.id');

    $viewer = memberWithPermissions($this, $owner, 'Channel Viewer', ['channels.view'], 'cv@example.com');
    $headers = headersFor($viewer['token'], $viewer['workspace_id']);

    $this->getJson('/api/channels', $headers)->assertOk();
    $this->getJson("/api/channels/{$channelId}", $headers)->assertOk();

    $this->postJson('/api/channels', whatsappChannelPayload('999888777'), $headers)->assertForbidden();
    $this->putJson("/api/channels/{$channelId}", ['name' => 'X'], $headers)->assertForbidden();
    $this->deleteJson("/api/channels/{$channelId}", [], $headers)->assertForbidden();
});

test('channels of another workspace are invisible', function () {
    $ownerA = registerOwner($this, 'a@example.com');
    $ownerB = registerOwner($this, 'b@example.com');

    $channelOfB = $this->postJson('/api/channels', whatsappChannelPayload(), headersFor($ownerB['token'], $ownerB['workspace_id']))
        ->json('data.id');

    $headersA = headersFor($ownerA['token'], $ownerA['workspace_id']);

    $this->getJson('/api/channels', $headersA)->assertOk()->assertJsonCount(0, 'data');
    $this->getJson("/api/channels/{$channelOfB}", $headersA)->assertNotFound();
    $this->putJson("/api/channels/{$channelOfB}", ['name' => 'X'], $headersA)->assertNotFound();
    $this->deleteJson("/api/channels/{$channelOfB}", [], $headersA)->assertNotFound();
});
