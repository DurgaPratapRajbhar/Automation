<?php

use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Workspace;
use App\Services\Agents\AgentRouter;
use App\Services\Agents\Sales\QualifierInterface;
use App\Services\InboundMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(RefreshDatabase::class);

// A pretend Sales model: gives the answers it is told to and remembers what it was asked.
function fakeQualifier(?array $result): object
{
    $qualifier = new class($result) implements QualifierInterface
    {
        public array $asked = [];

        public function __construct(private ?array $result) {}

        public function qualify(array $questions, string $transcript): ?array
        {
            $this->asked[] = [$questions, $transcript];

            return $this->result;
        }
    };

    app()->instance(QualifierInterface::class, $qualifier);

    return $qualifier;
}

function salesBusiness(TestCase $test, bool $flowOn = true): int
{
    $owner = registerOwner($test);
    whatsappTestChannel($owner['workspace_id']);

    Workspace::find($owner['workspace_id'])->update([
        'lead_flow_enabled' => $flowOn,
        'lead_questions' => ['What do you need?', 'What is your budget?'],
    ]);

    // every WhatsApp send gets its own id
    Http::fake(fn () => Http::response(['messages' => [['id' => 'wamid.out'.uniqid()]]]));

    return $owner['workspace_id'];
}

function leadWrites(string $text, string $from = '919876543210'): Conversation
{
    app(InboundMessageService::class)->handle([
        'metadata' => ['phone_number_id' => '444000111'],
        'contacts' => [['profile' => ['name' => 'Lead']]],
        'messages' => [['id' => 'wamid.'.uniqid(), 'from' => $from, 'type' => 'text', 'text' => ['body' => $text]]],
    ]);

    return Conversation::where('customer_phone', $from)->first();
}

function qualifierAnswer(array $answers, bool $done, string $score = 'hot'): array
{
    return ['answers' => $answers, 'reply' => $done ? 'Thanks, we will call you.' : 'What is your budget?', 'done' => $done, 'score' => $score, 'summary' => 'Wants a website'];
}

test('a new person gets the Sales agent: it asks the next question and remembers what it learned', function () {
    salesBusiness($this);
    $qualifier = fakeQualifier(qualifierAnswer(['a website', null], false));

    $chat = leadWrites('Hello, I need a website');

    expect($qualifier->asked)->toHaveCount(1)
        ->and($qualifier->asked[0][0])->toBe(['What do you need?', 'What is your budget?'])
        ->and($qualifier->asked[0][1])->toBe('Customer: Hello, I need a website');

    $chat->refresh();
    expect($chat->lead_status)->toBe('qualifying')
        ->and($chat->lead_answers)->toBe([
            ['question' => 'What do you need?', 'answer' => 'a website'],
            ['question' => 'What is your budget?', 'answer' => null],
        ]);

    $sent = Message::where('conversation_id', $chat->id)->where('direction', 'outbound')->first();
    expect($sent->body)->toBe('What is your budget?')->and($sent->payload['ai'])->toBeTrue();
});

test('when every question is answered the lead is scored and the chat goes back to support', function () {
    salesBusiness($this);
    fakeQualifier(qualifierAnswer(['a website', null], false));
    $chat = leadWrites('Hello, I need a website');

    $qualifier = fakeQualifier(qualifierAnswer(['a website', '20000'], true, 'hot'));
    leadWrites('Around 20000, this month', '919876543210');

    $chat->refresh();
    expect($chat->lead_status)->toBe('hot')
        ->and($chat->lead_summary)->toBe('Wants a website')
        ->and($qualifier->asked)->toHaveCount(1);

    // a third message is not the Sales agent's job any more
    leadWrites('Thanks', '919876543210');

    expect($qualifier->asked)->toHaveCount(1);
});

test('nothing happens when the flow is off', function () {
    salesBusiness($this, flowOn: false);
    $qualifier = fakeQualifier(qualifierAnswer([null, null], false));

    $chat = leadWrites('Hello');

    expect($qualifier->asked)->toBe([])
        ->and($chat->refresh()->lead_status)->toBeNull();
});

test('when the model gives no answer nothing is sent and the status is not set', function () {
    salesBusiness($this);
    fakeQualifier(null);

    $chat = leadWrites('Hello');

    expect(Message::where('conversation_id', $chat->id)->where('direction', 'outbound')->count())->toBe(0)
        ->and($chat->refresh()->lead_status)->toBeNull();
});

test('only a person\'s first message starts the flow: an old chat is left to support', function () {
    salesBusiness($this, flowOn: false);
    leadWrites('Hello'); // an old customer wrote before the flow was switched on

    Workspace::query()->update(['lead_flow_enabled' => true]);
    $qualifier = fakeQualifier(qualifierAnswer([null, null], false));

    leadWrites('I have a question');

    expect($qualifier->asked)->toBe([]);
});

test('the owner sets the questions: blank ones are dropped, at most three, and the switch is saved', function () {
    $owner = registerOwner($this);
    $headers = headersFor($owner['token'], $owner['workspace_id']);

    $this->putJson('/api/workspace/profile', ['lead_flow_enabled' => true, 'lead_questions' => ['What do you need?', '', ' Budget? ']], $headers)
        ->assertOk()
        ->assertJsonPath('data.lead_flow_enabled', true)
        ->assertJsonPath('data.lead_questions', ['What do you need?', 'Budget?'])
        ->assertJsonPath('data.max_lead_questions', 3);

    $this->putJson('/api/workspace/profile', ['lead_questions' => ['a', 'b', 'c', 'd']], $headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('lead_questions');

    $this->putJson('/api/workspace/profile', ['lead_questions' => [str_repeat('x', 201)]], $headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('lead_questions.0');
});

test('the Inbox shows what the Sales agent found, in the list and in the chat', function () {
    $workspace = salesBusiness($this);
    fakeQualifier(qualifierAnswer(['a website', '20000'], true, 'hot'));
    $chat = leadWrites('I need a website, budget 20000');

    $token = $this->postJson('/api/auth/login', ['email' => 'owner@example.com', 'password' => 'password123'])->json('data.access_token');
    $headers = headersFor($token, $workspace);

    $this->getJson('/api/conversations', $headers)
        ->assertOk()
        ->assertJsonPath('data.0.lead_status', 'hot');

    $this->getJson("/api/conversations/{$chat->id}", $headers)
        ->assertJsonPath('data.lead_status', 'hot')
        ->assertJsonPath('data.lead_summary', 'Wants a website')
        ->assertJsonPath('data.lead_answers.1.answer', '20000');
});

test('the owner gets a WhatsApp alert when a lead turns hot, but not for a warm one', function () {
    $workspace = salesBusiness($this);
    Workspace::find($workspace)->update(['phone' => '9000011111']);

    fakeQualifier(qualifierAnswer(['a website', '20000'], true, 'hot'));
    leadWrites('I need a website, budget 20000');

    Http::assertSent(fn ($request) => ($request['to'] ?? null) === '919000011111'
        && str_contains($request['text']['body'], 'Hot lead: Lead')
        && str_contains($request['text']['body'], 'Wants a website'));
});

test('a warm lead sends no alert', function () {
    $workspace = salesBusiness($this);
    Workspace::find($workspace)->update(['phone' => '9000011111']);

    fakeQualifier(qualifierAnswer(['a website', 'not sure'], true, 'warm'));
    leadWrites('Maybe a website, no budget yet');

    Http::assertNotSent(fn ($request) => ($request['to'] ?? null) === '919000011111');
});

test('the lead flow starts for the first message even when more messages arrived before it was handled', function () {
    $workspace = salesBusiness($this);
    $qualifier = fakeQualifier(qualifierAnswer(['a website', null], false));

    $chat = Conversation::create(['workspace_id' => $workspace, 'channel_id' => Channel::first()->id, 'customer_phone' => '919876543210', 'customer_name' => 'Lead']);
    $first = Message::create(['workspace_id' => $workspace, 'conversation_id' => $chat->id, 'direction' => 'inbound', 'type' => 'text', 'body' => 'Hi', 'status' => 'received']);
    Message::create(['workspace_id' => $workspace, 'conversation_id' => $chat->id, 'direction' => 'inbound', 'type' => 'text', 'body' => 'I need a website', 'status' => 'received']);

    app(AgentRouter::class)->replyTo($first->id);

    expect($qualifier->asked)->toHaveCount(1)->and($chat->refresh()->lead_status)->toBe('qualifying');
});
