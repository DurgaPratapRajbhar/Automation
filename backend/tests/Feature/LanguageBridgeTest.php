<?php

use App\Models\Conversation;
use App\Models\Message;
use App\Services\InboundMessageService;
use App\Services\Translation\TranslatorInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
});

// A pretend translator: says the text in the target language as "[Hindi] text" and which language it saw.
// $detected = what it reports as the original language; null makes it fail.
// $unchanged = it hands the text back as it is (what a real model does for text already in the target language).
function fakeTranslator(?string $detected = 'en', bool $unchanged = false): object
{
    $translator = new class($detected, $unchanged) implements TranslatorInterface
    {
        public array $asked = [];

        public function __construct(private ?string $detected, private bool $unchanged) {}

        public function translate(string $text, string $target): ?array
        {
            $this->asked[] = [$text, $target];

            return $this->detected === null
                ? null
                : ['translation' => $this->unchanged ? $text : "[{$target}] {$text}", 'language' => $this->detected];
        }
    };

    app()->instance(TranslatorInterface::class, $translator);

    return $translator;
}

// A business (Inbox language Hindi) with a connected number
function bridgeBusiness(TestCase $test): array
{
    $owner = registerOwner($test);
    whatsappTestChannel($owner['workspace_id']);

    return [headersFor($owner['token'], $owner['workspace_id']), $owner['workspace_id']];
}

function bridgeCustomerWrites(string $text, string $type = 'text', string $from = '919876543210'): int
{
    app(InboundMessageService::class)->handle([
        'metadata' => ['phone_number_id' => '444000111'],
        'contacts' => [['profile' => ['name' => 'Customer']]],
        'messages' => [['id' => 'wamid.'.uniqid(), 'from' => $from, 'type' => $type, 'text' => ['body' => $text]]],
    ]);

    return (int) Message::latest('id')->value('conversation_id');
}

// "Translate" clicked on the customer's (only) message of a chat
function bridgeTranslate(TestCase $test, array $headers, int $chat): TestResponse
{
    $message = Message::where('conversation_id', $chat)->latest('id')->first();

    return $test->postJson("/api/conversations/{$chat}/messages/{$message->id}/translate", [], $headers);
}

test('nothing is translated by itself', function () {
    [$headers] = bridgeBusiness($this);
    $translator = fakeTranslator('en');

    $chat = bridgeCustomerWrites('Hello, I need a website for my shop.');

    expect($translator->asked)->toBe([]);
    $this->getJson("/api/conversations/{$chat}", $headers)
        ->assertJsonPath('data.customer_language', null)
        ->assertJsonPath('data.messages.0.translation', null);
});

test('Translate gives a Hindi version, and the chat remembers the customer\'s language', function () {
    [$headers] = bridgeBusiness($this);
    $translator = fakeTranslator('en');
    $chat = bridgeCustomerWrites('Hello, I need a website for my shop.');

    bridgeTranslate($this, $headers, $chat)
        ->assertOk()
        ->assertJsonPath('data.language', 'en')
        ->assertJsonPath('data.translation', '[Hindi] Hello, I need a website for my shop.');

    expect($translator->asked)->toBe([['Hello, I need a website for my shop.', 'Hindi']]);

    $this->getJson("/api/conversations/{$chat}", $headers)
        ->assertJsonPath('data.customer_language', 'en')
        ->assertJsonPath('data.messages.0.translation', '[Hindi] Hello, I need a website for my shop.');
});

test('clicking Translate again costs nothing', function () {
    [$headers] = bridgeBusiness($this);
    $translator = fakeTranslator('en');
    $chat = bridgeCustomerWrites('Hello there');

    bridgeTranslate($this, $headers, $chat)->assertOk();
    bridgeTranslate($this, $headers, $chat)->assertOk()->assertJsonPath('data.translation', '[Hindi] Hello there');

    expect($translator->asked)->toHaveCount(1);
});

test('a message already in the Inbox language has nothing underneath', function () {
    [$headers] = bridgeBusiness($this);
    fakeTranslator('hi', unchanged: true);
    $chat = bridgeCustomerWrites('मुझे वेबसाइट चाहिए');

    bridgeTranslate($this, $headers, $chat)
        ->assertOk()
        ->assertJsonPath('data.language', 'hi')
        ->assertJsonPath('data.translation', null);

    $this->getJson("/api/conversations/{$chat}", $headers)->assertJsonPath('data.customer_language', 'hi');
});

test('a language answer that cannot be true is dropped: a translated message was not in the Inbox language', function () {
    [$headers] = bridgeBusiness($this);
    fakeTranslator('hi'); // the model named the language it translated INTO; the message was changed, so it was not Hindi
    $chat = bridgeCustomerWrites('Hello, I need a website.');

    bridgeTranslate($this, $headers, $chat)
        ->assertOk()
        ->assertJsonPath('data.language', null)
        ->assertJsonPath('data.translation', '[Hindi] Hello, I need a website.');

    $this->getJson("/api/conversations/{$chat}", $headers)->assertJsonPath('data.customer_language', null);
});

test('when the translation does not work the business is told and the message stays as it is', function () {
    [$headers] = bridgeBusiness($this);
    fakeTranslator(null);
    $chat = bridgeCustomerWrites('Hello');

    bridgeTranslate($this, $headers, $chat)
        ->assertStatus(503)
        ->assertJsonPath('message', 'The translation did not work this time. Please try again.');

    $this->getJson("/api/conversations/{$chat}", $headers)
        ->assertJsonPath('data.messages.0.body', 'Hello')
        ->assertJsonPath('data.messages.0.translation', null);
});

test('only text can be translated, and only in your own chats', function () {
    [$headers] = bridgeBusiness($this);
    $translator = fakeTranslator('en');

    $photo = bridgeCustomerWrites('', 'image');
    bridgeTranslate($this, $headers, $photo)->assertUnprocessable();

    $chat = bridgeCustomerWrites('Hello', from: '919000000001');
    $message = Message::where('conversation_id', $chat)->first();
    $other = registerOwner($this, 'two@example.com');

    $this->postJson("/api/conversations/{$chat}/messages/{$message->id}/translate", [], headersFor($other['token'], $other['workspace_id']))
        ->assertNotFound();
    $this->postJson("/api/conversations/{$photo}/messages/{$message->id}/translate", [], $headers)->assertNotFound();

    expect($translator->asked)->toBe([]);
});

test('the business can choose the language it reads in, and Translate uses it', function () {
    [$headers] = bridgeBusiness($this);
    $translator = fakeTranslator('en');

    $this->getJson('/api/workspace/me', $headers)
        ->assertJsonPath('data.workspace.inbox_language', 'hi')
        ->assertJsonPath('data.languages.en', 'English');
    $this->putJson('/api/workspace/profile', ['inbox_language' => 'mr'], $headers)
        ->assertOk()
        ->assertJsonPath('data.inbox_language', 'mr')
        ->assertJsonPath('data.languages.mr', 'Marathi');
    $this->putJson('/api/workspace/profile', ['inbox_language' => 'xx'], $headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('inbox_language');

    bridgeTranslate($this, $headers, bridgeCustomerWrites('Hello'));

    expect($translator->asked[0][1])->toBe('Marathi');
});

test('a reply written in Hindi is translated for the customer to look at before it is sent', function () {
    [$headers] = bridgeBusiness($this);
    $translator = fakeTranslator('en');
    $chat = bridgeCustomerWrites('Hello');
    bridgeTranslate($this, $headers, $chat);
    $translator->asked = [];

    $this->postJson("/api/conversations/{$chat}/translate", ['text' => 'जी बिल्कुल, बताइए क्या चाहिए?'], $headers)
        ->assertOk()
        ->assertJsonPath('data.to', 'en')
        ->assertJsonPath('data.to_name', 'English')
        ->assertJsonPath('data.text', '[English] जी बिल्कुल, बताइए क्या चाहिए?');

    expect($translator->asked)->toBe([['जी बिल्कुल, बताइए क्या चाहिए?', 'English']]);
});

test('the reply can be translated into another language than the customer\'s, and the choice is checked', function () {
    [$headers] = bridgeBusiness($this);
    fakeTranslator('en');
    $chat = bridgeCustomerWrites('Hello');

    $this->postJson("/api/conversations/{$chat}/translate", ['text' => 'ठीक है', 'to' => 'mr'], $headers)
        ->assertOk()
        ->assertJsonPath('data.to_name', 'Marathi');
    $this->postJson("/api/conversations/{$chat}/translate", ['text' => 'ठीक है', 'to' => 'xx'], $headers)->assertUnprocessable();
    $this->postJson("/api/conversations/{$chat}/translate", ['text' => ''], $headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('text');
});

test('without a known customer language the business must choose one, and a failed translation says so', function () {
    [$headers] = bridgeBusiness($this);
    fakeTranslator(null);
    $chat = bridgeCustomerWrites('Hello'); // the translator failed, so the language is unknown

    $this->postJson("/api/conversations/{$chat}/translate", ['text' => 'ठीक है'], $headers)
        ->assertUnprocessable()
        ->assertJsonPath('message', fn ($text) => str_contains($text, 'Choose the language'));

    $this->postJson("/api/conversations/{$chat}/translate", ['text' => 'ठीक है', 'to' => 'en'], $headers)
        ->assertStatus(503)
        ->assertJsonPath('message', 'The translation did not work this time. Please try again.');
});

test('a reply sent in the customer\'s language keeps what the owner wrote', function () {
    [$headers] = bridgeBusiness($this);
    fakeTranslator('en');
    $chat = bridgeCustomerWrites('Hello');

    // every sent message gets its own WhatsApp id
    Http::fake(fn () => Http::response(['messages' => [['id' => 'wamid.out'.uniqid()]]]));

    $this->postJson("/api/conversations/{$chat}/messages", [
        'body' => 'Sure, tell me what you need.',
        'original' => 'जी बिल्कुल, बताइए क्या चाहिए?',
        'language' => 'en',
    ], $headers)
        ->assertCreated()
        ->assertJsonPath('data.body', 'Sure, tell me what you need.')
        ->assertJsonPath('data.language', 'en')
        ->assertJsonPath('data.translation', 'जी बिल्कुल, बताइए क्या चाहिए?');

    // a plain reply stays plain
    $this->postJson("/api/conversations/{$chat}/messages", ['body' => 'Thanks'], $headers)
        ->assertCreated()
        ->assertJsonPath('data.translation', null);

    // the language of the translation must be given and known
    $this->postJson("/api/conversations/{$chat}/messages", ['body' => 'x', 'original' => 'y'], $headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('language');
});

test('a chat of another business cannot be translated into', function () {
    [$headers] = bridgeBusiness($this);
    fakeTranslator('en');
    $chat = bridgeCustomerWrites('Hello');
    $other = registerOwner($this, 'two@example.com');

    $this->postJson("/api/conversations/{$chat}/translate", ['text' => 'x'], headersFor($other['token'], $other['workspace_id']))
        ->assertNotFound();

    expect(Conversation::count())->toBe(1);
});

test('while testing, even a plain Inbox reply goes only to the allowed numbers', function () {
    [$headers] = bridgeBusiness($this);
    fakeTranslator('en');
    $chat = bridgeCustomerWrites('Hello'); // from 919876543210

    config(['campaigns.only_send_to' => ['918898540057']]);
    Http::fake();

    $this->postJson("/api/conversations/{$chat}/messages", ['body' => 'Hi'], $headers)
        ->assertCreated()
        ->assertJsonPath('data.status', 'failed')
        ->assertJsonPath('data.error', 'Sending is limited to test numbers right now, so this message was not sent.');

    Http::assertNothingSent();
});

test('a message that was translated, even with an unknown language, is not translated again', function () {
    [$headers] = bridgeBusiness($this);
    $translator = fakeTranslator('hi'); // the model names the wrong language, so the language stays unknown
    $chat = bridgeCustomerWrites('Hello, I need a website.');

    bridgeTranslate($this, $headers, $chat)->assertOk()->assertJsonPath('data.language', null);
    bridgeTranslate($this, $headers, $chat)->assertOk()->assertJsonPath('data.translation', '[Hindi] Hello, I need a website.');

    expect($translator->asked)->toHaveCount(1);
});
