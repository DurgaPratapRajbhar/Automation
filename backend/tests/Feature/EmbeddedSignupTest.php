<?php

use App\Models\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    config([
        'services.meta.app_id' => 'APP123',
        'services.meta.app_secret' => 'SECRET123',
        'services.meta.graph_url' => 'https://graph.facebook.com',
        'services.meta.graph_version' => 'v25.0',
    ]);
});

test('connect turns the popup code into a working channel', function () {
    $owner = registerOwner($this);

    $payload = ['code' => 'CODE_FROM_POPUP', 'waba_id' => '111222333', 'phone_number_id' => '555000111'];
    Http::fake(function (Request $request) {
        $url = $request->url();

        return match (true) {
            str_contains($url, '/oauth/access_token') => Http::response(['access_token' => 'BIZ_TOKEN']),
            str_contains($url, '/subscribed_apps'), str_contains($url, '/register') => Http::response(['success' => true]),
            str_contains($url, '/555000111') => Http::response(['verified_name' => 'Sharma Kirana', 'display_phone_number' => '+91 90000 00001']),
            default => Http::response([], 500),
        };
    });

    $this->postJson('/api/channels/connect', $payload, headersFor($owner['token'], $owner['workspace_id']))
        ->assertCreated()
        ->assertJsonPath('message', 'WhatsApp connected successfully.')
        ->assertJsonPath('data.name', 'Sharma Kirana +91 90000 00001')
        ->assertJsonPath('data.phone_number_id', '555000111')
        ->assertJsonPath('data.waba_id', '111222333')
        ->assertJsonPath('data.has_access_token', true);

    $channel = Channel::with('credential')->where('phone_number_id', '555000111')->first();
    expect($channel->workspace_id)->toBe($owner['workspace_id'])
        ->and($channel->credential->access_token)->toBe('BIZ_TOKEN');

    $pin = Crypt::decryptString($channel->credential->config['pin']);
    expect($pin)->toMatch('/^\d{6}$/');

    // Code exchanged with our app id + secret, then subscribe and register with the business token
    Http::assertSent(fn (Request $r) => str_contains($r->url(), '/oauth/access_token')
        && str_contains($r->url(), 'client_id=APP123')
        && str_contains($r->url(), 'client_secret=SECRET123')
        && str_contains($r->url(), 'code=CODE_FROM_POPUP'));
    Http::assertSent(fn (Request $r) => str_contains($r->url(), '/111222333/subscribed_apps')
        && $r->hasHeader('Authorization', 'Bearer BIZ_TOKEN'));
    Http::assertSent(fn (Request $r) => str_contains($r->url(), '/555000111/register')
        && $r['messaging_product'] === 'whatsapp'
        && $r['pin'] === $pin);
});

test('a rejected code gives a clear error and creates nothing', function () {
    $owner = registerOwner($this);

    Http::fake(['*' => Http::response(['error' => ['message' => 'This authorization code has expired.']], 400)]);

    $this->postJson('/api/channels/connect', ['code' => 'OLD', 'waba_id' => '111222333', 'phone_number_id' => '555000111'], headersFor($owner['token'], $owner['workspace_id']))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Meta did not accept the request: This authorization code has expired.');

    expect(Channel::count())->toBe(0);
});

test('a number that is already connected is refused before calling Meta', function () {
    $owner = registerOwner($this);
    Channel::create([
        'workspace_id' => $owner['workspace_id'],
        'type' => 'whatsapp',
        'provider' => 'meta',
        'name' => 'Existing',
        'status' => 'connected',
        'phone_number_id' => '555000111',
    ]);
    Http::fake();

    $this->postJson('/api/channels/connect', ['code' => 'X', 'waba_id' => '111222333', 'phone_number_id' => '555000111'], headersFor($owner['token'], $owner['workspace_id']))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'This WhatsApp number is already connected to a channel.');

    Http::assertNothingSent();
});

test('connecting needs the channels.connect permission', function () {
    $owner = registerOwner($this);
    $staff = memberWithPermissions($this, $owner, 'Channel Viewer', ['channels.view'], 'cv@example.com');
    Http::fake();

    $this->postJson('/api/channels/connect', ['code' => 'X', 'waba_id' => '111222333', 'phone_number_id' => '555000111'], headersFor($staff['token'], $staff['workspace_id']))
        ->assertForbidden();

    Http::assertNothingSent();
});

test('without app id or secret on the server the signup is refused clearly', function () {
    $owner = registerOwner($this);
    config(['services.meta.app_id' => null]);
    Http::fake();

    $this->postJson('/api/channels/connect', ['code' => 'X', 'waba_id' => '111222333', 'phone_number_id' => '555000111'], headersFor($owner['token'], $owner['workspace_id']))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'WhatsApp signup is not set up on the server yet.');

    Http::assertNothingSent();
});

test('the channel list gives the browser the public popup settings, never the secret', function () {
    $owner = registerOwner($this);
    config(['services.meta.embedded_signup_config_id' => 'CFG9']);

    $response = $this->getJson('/api/channels', headersFor($owner['token'], $owner['workspace_id']))
        ->assertOk()
        ->assertJsonPath('embedded_signup.app_id', 'APP123')
        ->assertJsonPath('embedded_signup.config_id', 'CFG9')
        ->assertJsonPath('embedded_signup.graph_version', 'v25.0');

    expect($response->getContent())->not->toContain('SECRET123');
});
