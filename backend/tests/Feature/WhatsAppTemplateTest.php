<?php

use App\Models\Channel;
use App\Models\ChannelCredential;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    config(['services.meta.graph_url' => 'https://graph.facebook.com', 'services.meta.graph_version' => 'v25.0']);
});

// Connected channel of the owner's shop, with a WABA id and a token
function templateChannel(array $owner): Channel
{
    $channel = Channel::create([
        'workspace_id' => $owner['workspace_id'],
        'type' => 'whatsapp',
        'provider' => 'meta',
        'name' => 'Shop WA',
        'external_account_id' => '777888999',
        'status' => 'connected',
        'phone_number_id' => '123450000',
    ]);

    ChannelCredential::create([
        'channel_id' => $channel->id,
        'access_token' => 'SHOP_TOKEN',
        'config' => ['phone_number_id' => '123450000', 'waba_id' => '777888999'],
    ]);

    return $channel;
}

function templatePayload(array $overrides = []): array
{
    return [
        'name' => 'order_ready',
        'category' => 'UTILITY',
        'language' => 'en',
        'body' => 'Namaste {{1}}, your order {{2}} is ready.',
        'examples' => ['Ramesh', 'A-101'],
        ...$overrides,
    ];
}

test('a template is sent to Meta with its examples and comes back pending', function () {
    $owner = registerOwner($this);
    $channel = templateChannel($owner);
    Http::fake(['*/777888999/message_templates' => Http::response(['id' => 'T1', 'status' => 'PENDING', 'category' => 'UTILITY'])]);

    $this->postJson("/api/channels/{$channel->id}/templates", templatePayload(), headersFor($owner['token'], $owner['workspace_id']))
        ->assertCreated()
        ->assertJsonPath('message', 'Template sent to Meta for approval.')
        ->assertJsonPath('data.id', 'T1')
        ->assertJsonPath('data.status', 'PENDING')
        ->assertJsonPath('data.name', 'order_ready');

    Http::assertSent(fn (Request $r) => $r->method() === 'POST'
        && str_contains($r->url(), '/777888999/message_templates')
        && $r->hasHeader('Authorization', 'Bearer SHOP_TOKEN')
        && $r['name'] === 'order_ready'
        && $r['category'] === 'UTILITY'
        && $r['language'] === 'en'
        && $r['components'][0]['text'] === 'Namaste {{1}}, your order {{2}} is ready.'
        && $r['components'][0]['example']['body_text'] === [['Ramesh', 'A-101']]);
});

test('templates are listed from Meta in our own shape', function () {
    $owner = registerOwner($this);
    $channel = templateChannel($owner);
    Http::fake(['*/777888999/message_templates*' => Http::response(['data' => [[
        'id' => 'T1', 'name' => 'order_ready', 'status' => 'APPROVED', 'category' => 'UTILITY', 'language' => 'en',
        'components' => [['type' => 'HEADER', 'format' => 'TEXT', 'text' => 'Hi'], ['type' => 'BODY', 'text' => 'Your order is ready.']],
    ]]])]);

    $this->getJson("/api/channels/{$channel->id}/templates", headersFor($owner['token'], $owner['workspace_id']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'APPROVED')
        ->assertJsonPath('data.0.body', 'Your order is ready.');
});

test('wrong examples or gaps in {{n}} are caught before calling Meta', function () {
    $owner = registerOwner($this);
    $channel = templateChannel($owner);
    $headers = headersFor($owner['token'], $owner['workspace_id']);
    Http::fake();

    $this->postJson("/api/channels/{$channel->id}/templates", templatePayload(['examples' => ['Ramesh']]), $headers)
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Give exactly 2 example value(s), one for each {{n}} in the text.');

    $this->postJson("/api/channels/{$channel->id}/templates", templatePayload(['body' => 'Hi {{2}}', 'examples' => ['x']]), $headers)
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Number the blanks {{1}}, {{2}}, {{3}}... in order, with no gaps.');

    $this->postJson("/api/channels/{$channel->id}/templates", templatePayload(['name' => 'Order Ready']), $headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);

    Http::assertNothingSent();
});

test('a template can be deleted by name', function () {
    $owner = registerOwner($this);
    $channel = templateChannel($owner);
    Http::fake(['*' => Http::response(['success' => true])]);

    $this->deleteJson("/api/channels/{$channel->id}/templates/order_ready", [], headersFor($owner['token'], $owner['workspace_id']))
        ->assertOk()
        ->assertJsonPath('message', 'Template deleted.');

    Http::assertSent(fn (Request $r) => $r->method() === 'DELETE'
        && str_contains($r->url(), '/777888999/message_templates?name=order_ready'));
});

test('another shop cannot use this channel for templates', function () {
    $ownerA = registerOwner($this, 'a@example.com');
    $ownerB = registerOwner($this, 'b@example.com');
    $channelOfB = templateChannel($ownerB);
    Http::fake();

    $this->getJson("/api/channels/{$channelOfB->id}/templates", headersFor($ownerA['token'], $ownerA['workspace_id']))
        ->assertNotFound();

    Http::assertNothingSent();
});

test('template actions need their permissions', function () {
    $owner = registerOwner($this);
    $channel = templateChannel($owner);
    $viewer = memberWithPermissions($this, $owner, 'Template Viewer', ['templates.view'], 'tv@example.com');
    $headers = headersFor($viewer['token'], $viewer['workspace_id']);
    Http::fake(['*' => Http::response(['data' => []])]);

    $this->getJson("/api/channels/{$channel->id}/templates", $headers)->assertOk();
    $this->postJson("/api/channels/{$channel->id}/templates", templatePayload(), $headers)->assertForbidden();
    $this->deleteJson("/api/channels/{$channel->id}/templates/order_ready", [], $headers)->assertForbidden();
});
