<?php

use App\Models\Channel;
use App\Models\ChannelCredential;
use App\Models\Message;
use App\Services\InboundMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    config(['services.meta.graph_url' => 'https://graph.facebook.com', 'services.meta.graph_version' => 'v25.0']);
});

// Shop with a connected number (WABA 555666777) and one customer chat; returns the chat id
function chatWithCustomer(array $owner): int
{
    $channel = Channel::create([
        'workspace_id' => $owner['workspace_id'],
        'type' => 'whatsapp',
        'provider' => 'meta',
        'name' => 'Shop WA',
        'external_account_id' => '555666777',
        'status' => 'connected',
        'phone_number_id' => '444000111',
    ]);

    ChannelCredential::create([
        'channel_id' => $channel->id,
        'access_token' => 'SHOP_TOKEN',
        'config' => ['phone_number_id' => '444000111', 'waba_id' => '555666777'],
    ]);

    app(InboundMessageService::class)->handle([
        'metadata' => ['phone_number_id' => '444000111'],
        'contacts' => [['profile' => ['name' => 'Ramesh']]],
        'messages' => [['id' => 'wamid.'.uniqid(), 'from' => '919876543210', 'type' => 'text', 'text' => ['body' => 'order kab milega?']]],
    ]);

    return (int) Message::latest('id')->value('conversation_id');
}

// Fake Meta: the template lookup answers with $status, sending a message succeeds
function fakeTemplateMeta(string $status = 'APPROVED'): void
{
    Http::fake(function (Request $request) use ($status) {
        if (str_contains($request->url(), '/555666777/message_templates')) {
            return Http::response(['data' => [[
                'name' => 'order_ready', 'status' => $status, 'language' => 'en',
                'components' => [['type' => 'BODY', 'text' => 'Namaste {{1}}, your order {{2}} is ready.']],
            ]]]);
        }

        if (str_contains($request->url(), '/444000111/messages')) {
            return Http::response(['messages' => [['id' => 'wamid.tpl1']]]);
        }

        return Http::response([], 500);
    });
}

test('an approved template is sent with its values and saved in the inbox filled in', function () {
    $owner = registerOwner($this);
    $chatId = chatWithCustomer($owner);
    fakeTemplateMeta();

    $this->postJson("/api/conversations/{$chatId}/template", [
        'name' => 'order_ready',
        'language' => 'en',
        'parameters' => ['Ramesh', 'A-101'],
    ], headersFor($owner['token'], $owner['workspace_id']))
        ->assertCreated()
        ->assertJsonPath('message', 'Template sent.')
        ->assertJsonPath('data.type', 'template')
        ->assertJsonPath('data.body', 'Namaste Ramesh, your order A-101 is ready.')
        ->assertJsonPath('data.status', 'sent')
        ->assertJsonPath('data.sent_by', 'Owner');

    Http::assertSent(fn (Request $r) => str_contains($r->url(), '/444000111/messages')
        && $r['type'] === 'template'
        && $r['to'] === '919876543210'
        && $r['template']['name'] === 'order_ready'
        && $r['template']['language']['code'] === 'en'
        && $r['template']['components'][0]['parameters'] === [
            ['type' => 'text', 'text' => 'Ramesh'],
            ['type' => 'text', 'text' => 'A-101'],
        ]);
});

test('a template that is not approved yet is refused and nothing is sent', function () {
    $owner = registerOwner($this);
    $chatId = chatWithCustomer($owner);
    fakeTemplateMeta('PENDING');

    $this->postJson("/api/conversations/{$chatId}/template", [
        'name' => 'order_ready', 'language' => 'en', 'parameters' => ['Ramesh', 'A-101'],
    ], headersFor($owner['token'], $owner['workspace_id']))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Template order_ready is not approved by Meta yet.');

    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/messages'));
    expect(Message::where('type', 'template')->exists())->toBeFalse();
});

test('missing values for the blanks are refused', function () {
    $owner = registerOwner($this);
    $chatId = chatWithCustomer($owner);
    fakeTemplateMeta();

    $this->postJson("/api/conversations/{$chatId}/template", [
        'name' => 'order_ready', 'language' => 'en', 'parameters' => ['Ramesh'],
    ], headersFor($owner['token'], $owner['workspace_id']))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Fill all 2 blank(s) of the template.');

    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/messages'));
});

test('the chat tells whether the 24-hour window is open', function () {
    $owner = registerOwner($this);
    $chatId = chatWithCustomer($owner);
    $headers = headersFor($owner['token'], $owner['workspace_id']);

    $this->getJson("/api/conversations/{$chatId}", $headers)->assertJsonPath('data.window_open', true);

    Message::where('conversation_id', $chatId)->update(['created_at' => now()->subHours(25)]);

    $this->getJson("/api/conversations/{$chatId}", $headers)->assertJsonPath('data.window_open', false);
});

test('sending a template needs the messages.template permission', function () {
    $owner = registerOwner($this);
    $chatId = chatWithCustomer($owner);
    $staff = memberWithPermissions($this, $owner, 'Replier', ['conversations.view', 'messages.create'], 'rp@example.com');
    Http::fake();

    $this->postJson("/api/conversations/{$chatId}/template", [
        'name' => 'order_ready', 'language' => 'en', 'parameters' => ['Ramesh', 'A-101'],
    ], headersFor($staff['token'], $staff['workspace_id']))
        ->assertForbidden();

    Http::assertNothingSent();
});
