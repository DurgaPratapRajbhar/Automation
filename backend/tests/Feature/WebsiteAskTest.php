<?php

use App\Models\Workspace;
use App\Services\Contracts\AiResponderInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
});

// AI that answers with the workspace it was asked for, so the test sees which Knowledge was used
function fakeAi(?string $answer = 'Haan, delivery free hai.'): void
{
    app()->bind(AiResponderInterface::class, fn () => new class($answer) implements AiResponderInterface
    {
        public function __construct(private ?string $reply) {}

        public function answer(int $workspaceId, string $question, string $replyStyle = 'auto'): ?string
        {
            return $this->reply === null ? null : "[{$workspaceId}] {$this->reply}";
        }
    });
}

function newApiKey(\Tests\TestCase $test, array $owner): string
{
    return $test->postJson('/api/workspace/api-key', [], headersFor($owner['token'], $owner['workspace_id']))
        ->assertCreated()
        ->json('data.key');
}

test('owner makes a key; only its hash is stored and the full key is shown once', function () {
    $owner = registerOwner($this);
    $headers = headersFor($owner['token'], $owner['workspace_id']);

    $this->getJson('/api/workspace/api-key', $headers)
        ->assertOk()
        ->assertJsonPath('data.has_key', false);

    $key = newApiKey($this, $owner);

    expect($key)->toStartWith('sdp_')
        ->and(Workspace::find($owner['workspace_id'])->api_key_hash)->toBe(hash('sha256', $key));

    $this->getJson('/api/workspace/api-key', $headers)
        ->assertOk()
        ->assertJsonPath('data.has_key', true)
        ->assertJsonPath('data.hint', substr($key, -4))
        ->assertJsonMissingPath('data.key');
});

test('the website gets an answer from its own workspace AI', function () {
    fakeAi();
    $owner = registerOwner($this);
    $key = newApiKey($this, $owner);

    $this->postJson('/api/v1/ask', ['question' => 'Delivery charge kitna hai?'], ['X-Api-Key' => $key])
        ->assertOk()
        ->assertJsonPath('data.answer', "[{$owner['workspace_id']}] Haan, delivery free hai.");
});

test('a missing, wrong, replaced or removed key gets no answer', function () {
    fakeAi();
    $owner = registerOwner($this);
    $headers = headersFor($owner['token'], $owner['workspace_id']);
    $ask = fn (?string $key) => $this->postJson('/api/v1/ask', ['question' => 'hi'], $key ? ['X-Api-Key' => $key] : []);

    $ask(null)->assertUnauthorized()->assertJsonPath('message', 'Invalid or missing API key.');
    $ask('sdp_wrong')->assertUnauthorized();

    $old = newApiKey($this, $owner);
    $new = newApiKey($this, $owner);
    $ask($old)->assertUnauthorized();
    $ask($new)->assertOk();

    $this->deleteJson('/api/workspace/api-key', [], $headers)
        ->assertOk()
        ->assertJsonPath('data.has_key', false);
    $ask($new)->assertUnauthorized();
});

test('a suspended shop stops answering', function () {
    fakeAi();
    $owner = registerOwner($this);
    $key = newApiKey($this, $owner);

    Workspace::whereKey($owner['workspace_id'])->update(['status' => 'suspended']);

    $this->postJson('/api/v1/ask', ['question' => 'hi'], ['X-Api-Key' => $key])->assertUnauthorized();
});

test('a question is required and an AI failure is reported', function () {
    $owner = registerOwner($this);
    $key = newApiKey($this, $owner);

    $this->postJson('/api/v1/ask', [], ['X-Api-Key' => $key])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('question');

    fakeAi(null);

    $this->postJson('/api/v1/ask', ['question' => 'hi'], ['X-Api-Key' => $key])
        ->assertStatus(503)
        ->assertJsonPath('message', 'The assistant could not answer right now. Please try again.');
});

test('the reply language is saved on the shop profile and only accepts known styles', function () {
    $owner = registerOwner($this);
    $headers = headersFor($owner['token'], $owner['workspace_id']);

    $this->putJson('/api/workspace/profile', ['reply_style' => 'hinglish'], $headers)
        ->assertOk()
        ->assertJsonPath('data.reply_style', 'hinglish');

    $this->putJson('/api/workspace/profile', ['reply_style' => 'french'], $headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('reply_style');
});
