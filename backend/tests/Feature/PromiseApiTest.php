<?php

use App\Models\Conversation;
use App\Models\Promise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(RefreshDatabase::class);

function promiseOwner(TestCase $test, string $email = 'owner@example.com'): array
{
    $owner = registerOwner($test, $email);

    return [headersFor($owner['token'], $owner['workspace_id']), $owner['workspace_id']];
}

function savedPromise(int $workspace, string $due, array $extra = []): Promise
{
    return Promise::create([
        'workspace_id' => $workspace,
        'customer_name' => 'Aarav',
        'kind' => 'payment',
        'quote' => 'kal dunga',
        'due_on' => $due,
        'source' => 'agent',
        ...$extra,
    ]);
}

test('the list shows open promises, earliest date first, with overdue / today / later', function () {
    [$headers, $workspace] = promiseOwner($this);
    $today = now('Asia/Kolkata');

    savedPromise($workspace, $today->copy()->addDays(3)->toDateString());
    savedPromise($workspace, $today->copy()->subDay()->toDateString());
    savedPromise($workspace, $today->toDateString());
    savedPromise($workspace, $today->toDateString(), ['status' => 'done']);

    $response = $this->getJson('/api/promises', $headers)->assertOk();

    expect(collect($response->json('data'))->pluck('timing')->all())->toBe(['overdue', 'today', 'later']);

    $this->getJson('/api/promises?status=done', $headers)->assertJsonCount(1, 'data')->assertJsonPath('data.0.timing', null);
    $this->getJson('/api/promises?status=all', $headers)->assertJsonCount(4, 'data');
});

test('the owner adds a promise by hand, from a chat or typed in', function () {
    [$headers, $workspace] = promiseOwner($this);
    $chat = Conversation::create(['workspace_id' => $workspace, 'channel_id' => whatsappTestChannel($workspace)->id, 'customer_phone' => '919876543210', 'customer_name' => 'Meena']);

    $this->postJson('/api/promises', ['kind' => 'payment', 'quote' => 'Friday ko 500', 'amount' => 500, 'due_on' => '2026-11-06', 'conversation_id' => $chat->id], $headers)
        ->assertCreated()
        ->assertJsonPath('data.customer_name', 'Meena')
        ->assertJsonPath('data.customer_phone', '919876543210')
        ->assertJsonPath('data.source', 'manual');

    $this->postJson('/api/promises', ['kind' => 'callback', 'quote' => 'will call', 'due_on' => '2026-11-07', 'customer_name' => 'Ravi'], $headers)
        ->assertCreated()
        ->assertJsonPath('data.customer_name', 'Ravi');

    $this->postJson('/api/promises', ['kind' => 'gift', 'quote' => '', 'due_on' => 'tomorrow'], $headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['kind', 'quote', 'due_on']);
});

test('a promise is marked done, not coming, or open again', function () {
    [$headers, $workspace] = promiseOwner($this);
    $promise = savedPromise($workspace, '2026-11-05');

    $this->putJson("/api/promises/{$promise->id}", ['status' => 'done'], $headers)->assertOk()->assertJsonPath('data.status', 'done');
    $this->putJson("/api/promises/{$promise->id}", ['status' => 'missed'], $headers)->assertJsonPath('data.status', 'missed');
    $this->putJson("/api/promises/{$promise->id}", ['status' => 'open'], $headers)->assertJsonPath('data.status', 'open');
    $this->putJson("/api/promises/{$promise->id}", ['status' => 'maybe'], $headers)->assertUnprocessable();
});

test('one business cannot see or change another business\'s promises', function () {
    [$headers, $workspace] = promiseOwner($this);
    [$otherHeaders] = promiseOwner($this, 'two@example.com');
    $promise = savedPromise($workspace, '2026-11-05');

    $this->getJson('/api/promises?status=all', $otherHeaders)->assertJsonCount(0, 'data');
    $this->putJson("/api/promises/{$promise->id}", ['status' => 'done'], $otherHeaders)->assertNotFound();
    $this->postJson('/api/promises', ['kind' => 'payment', 'quote' => 'x', 'due_on' => '2026-11-05', 'conversation_id' => 999], $headers)->assertNotFound();
});

test('the summary counts what needs the owner today', function () {
    [$headers, $workspace] = promiseOwner($this);
    $today = now('Asia/Kolkata');
    $channel = whatsappTestChannel($workspace);

    savedPromise($workspace, $today->copy()->subDays(2)->toDateString());
    savedPromise($workspace, $today->toDateString());
    savedPromise($workspace, $today->toDateString(), ['status' => 'done']);
    savedPromise($workspace, $today->copy()->addDay()->toDateString());
    Conversation::create(['workspace_id' => $workspace, 'channel_id' => $channel->id, 'customer_phone' => '919000000001', 'lead_status' => 'hot', 'unread_count' => 2]);
    Conversation::create(['workspace_id' => $workspace, 'channel_id' => $channel->id, 'customer_phone' => '919000000002', 'lead_status' => 'warm']);

    $this->getJson('/api/promises/summary', $headers)
        ->assertOk()
        ->assertJsonPath('data.overdue', 1)
        ->assertJsonPath('data.today', 1)
        ->assertJsonPath('data.hot_leads', 1)
        ->assertJsonPath('data.unread', 1)
        ->assertJsonPath('data.needs_ok', 0)
        ->assertJsonPath('data.escalated', 0);
});
