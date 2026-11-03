<?php

use App\Models\AgentAction;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Order;
use App\Models\Promise;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(RefreshDatabase::class);

// A business with a connected number, a customer chat, and Meta accepting every send
function remindBusiness(TestCase $test, bool $customerWroteRecently = true): array
{
    $owner = registerOwner($test);
    $channel = whatsappTestChannel($owner['workspace_id']);
    $chat = Conversation::create(['workspace_id' => $owner['workspace_id'], 'channel_id' => $channel->id, 'customer_phone' => '919876543210', 'customer_name' => 'Aarav']);

    $message = Message::create([
        'workspace_id' => $owner['workspace_id'], 'conversation_id' => $chat->id, 'direction' => 'inbound', 'type' => 'text',
        'body' => 'kal dunga', 'status' => 'received',
    ]);
    $message->forceFill(['created_at' => $customerWroteRecently ? now() : now()->subDays(3)])->save();

    Http::fake(fn () => Http::response(['messages' => [['id' => 'wamid.out'.uniqid()]]]));

    return [headersFor($owner['token'], $owner['workspace_id']), $owner['workspace_id'], $chat];
}

function latePayment(int $workspace, ?Conversation $chat, array $extra = []): Promise
{
    return Promise::create([
        'workspace_id' => $workspace,
        'conversation_id' => $chat?->id,
        'customer_name' => 'Aarav',
        'customer_phone' => '919876543210',
        'kind' => 'payment',
        'quote' => 'kal dunga',
        'amount' => 1200,
        'due_on' => now('Asia/Kolkata')->subDays(2)->toDateString(),
        'source' => 'agent',
        ...$extra,
    ]);
}

test('late payments get a reminder proposed, once, and nothing is sent yet', function () {
    [$headers, $workspace, $chat] = remindBusiness($this);
    latePayment($workspace, $chat);
    latePayment($workspace, $chat, ['kind' => 'callback']);                                         // not a payment
    latePayment($workspace, $chat, ['due_on' => now('Asia/Kolkata')->addDay()->toDateString()]);    // not late yet
    latePayment($workspace, $chat, ['status' => 'done']);                                           // already paid
    latePayment($workspace, null);                                                                  // no chat to write in

    Artisan::call('agents:propose-reminders');
    Artisan::call('agents:propose-reminders'); // the next morning: not asked again

    $this->getJson('/api/actions', $headers)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'reminder.send')
        ->assertJsonPath('data.0.agent', 'finance')
        ->assertJsonPath('data.0.conversation_id', $chat->id);

    expect(AgentAction::first()->summary)->toContain('Aarav')->toContain('₹1,200.00');
    Http::assertNothingSent();
});

test('when the owner says yes the customer gets the reminder', function () {
    [$headers, $workspace, $chat] = remindBusiness($this);
    latePayment($workspace, $chat);
    Artisan::call('agents:propose-reminders');
    $action = $this->getJson('/api/actions', $headers)->json('data.0.id');

    $this->putJson("/api/actions/{$action}", ['decision' => 'approve'], $headers)->assertOk()->assertJsonPath('data.status', 'executed');

    $sent = Message::where('conversation_id', $chat->id)->where('direction', 'outbound')->first();
    expect($sent->body)->toContain('Namaste Aarav')->toContain('₹1,200.00');
});

test('a reminder to a customer who has not written for a day is escalated: only a template is allowed', function () {
    [$headers, $workspace, $chat] = remindBusiness($this, customerWroteRecently: false);
    latePayment($workspace, $chat);
    Artisan::call('agents:propose-reminders');
    $action = $this->getJson('/api/actions', $headers)->json('data.0.id');

    $this->putJson("/api/actions/{$action}", ['decision' => 'approve'], $headers)
        ->assertJsonPath('data.status', 'escalated')
        ->assertJsonPath('data.error', fn ($error) => str_contains($error, 'approved template'));

    expect(Message::where('direction', 'outbound')->count())->toBe(0);
});

test('a promise that was paid after the agent asked gets no reminder', function () {
    [$headers, $workspace, $chat] = remindBusiness($this);
    $promise = latePayment($workspace, $chat);
    Artisan::call('agents:propose-reminders');
    $promise->update(['status' => 'done']);
    $action = $this->getJson('/api/actions', $headers)->json('data.0.id');

    $this->putJson("/api/actions/{$action}", ['decision' => 'approve'], $headers)->assertJsonPath('data.status', 'escalated');

    expect(Message::where('direction', 'outbound')->count())->toBe(0);
});

test('the daily report is proposed once a day, to a business that has an owner number', function () {
    [$headers, $workspace] = remindBusiness($this);

    Artisan::call('agents:propose-reports');
    expect(AgentAction::count())->toBe(0); // no phone number saved yet

    Workspace::find($workspace)->update(['phone' => '98765 43210']);
    Artisan::call('agents:propose-reports');
    Artisan::call('agents:propose-reports');

    $this->getJson('/api/actions', $headers)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'report.send')
        ->assertJsonPath('data.0.summary', "Send today's report to your WhatsApp (+919876543210)");
});

test('when the owner says yes the report with today\'s numbers goes to the owner\'s number', function () {
    [$headers, $workspace, $chat] = remindBusiness($this);
    Workspace::find($workspace)->update(['phone' => '9876543210']);
    Order::create(['workspace_id' => $workspace, 'conversation_id' => $chat->id, 'status' => 'confirmed', 'confirmed_at' => now(), 'total' => 900]);
    Order::create(['workspace_id' => $workspace, 'conversation_id' => $chat->id, 'status' => 'confirmed', 'confirmed_at' => now(), 'total' => 100.5]);
    Order::create(['workspace_id' => $workspace, 'conversation_id' => $chat->id, 'status' => 'draft', 'total' => 5000]);
    latePayment($workspace, $chat);

    Artisan::call('agents:propose-reports');
    $action = $this->getJson('/api/actions', $headers)->json('data.0.id');

    $this->putJson("/api/actions/{$action}", ['decision' => 'approve'], $headers)->assertOk()->assertJsonPath('data.status', 'executed');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/messages')
        && $request['to'] === '919876543210'
        && str_contains($request['text']['body'], 'Orders confirmed: 2 (₹1,000.50)')
        && str_contains($request['text']['body'], 'Promises late: 1'));
});

test('a report with no number to send to, or no connected WhatsApp, is escalated', function () {
    [$headers, $workspace] = remindBusiness($this);
    Workspace::find($workspace)->update(['phone' => '9876543210']);
    Artisan::call('agents:propose-reports');
    $action = $this->getJson('/api/actions', $headers)->json('data.0.id');

    Workspace::find($workspace)->update(['phone' => null]);

    $this->putJson("/api/actions/{$action}", ['decision' => 'approve'], $headers)
        ->assertJsonPath('data.status', 'escalated')
        ->assertJsonPath('data.error', 'Add your phone number in Business profile, so the report can be sent to you.');
});

test('while testing, the report goes only to the allowed numbers', function () {
    [$headers, $workspace] = remindBusiness($this);
    Workspace::find($workspace)->update(['phone' => '9876543210']);
    Artisan::call('agents:propose-reports');
    $action = $this->getJson('/api/actions', $headers)->json('data.0.id');

    config(['campaigns.only_send_to' => ['918898540057']]);

    $this->putJson("/api/actions/{$action}", ['decision' => 'approve'], $headers)->assertJsonPath('data.status', 'escalated');

    Http::assertNothingSent();
});

test('a reminder for a promise whose chat was deleted is escalated with a clear reason', function () {
    [$headers, $workspace, $chat] = remindBusiness($this);
    latePayment($workspace, $chat);
    Artisan::call('agents:propose-reminders');
    $chat->delete();
    $action = $this->getJson('/api/actions', $headers)->json('data.0.id');

    $this->putJson("/api/actions/{$action}", ['decision' => 'approve'], $headers)
        ->assertJsonPath('data.status', 'escalated')
        ->assertJsonPath('data.error', 'This promise is already closed.');
});
