<?php

use App\Models\AgentAction;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Order;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(RefreshDatabase::class);

// A business with a customer chat (the customer wrote just now), and Meta accepting every send
function followUpBusiness(TestCase $test, bool $customerWroteRecently = true): array
{
    $owner = registerOwner($test);
    $channel = whatsappTestChannel($owner['workspace_id']);
    $chat = Conversation::create(['workspace_id' => $owner['workspace_id'], 'channel_id' => $channel->id, 'customer_phone' => '919876543210', 'customer_name' => 'Meena']);

    $message = Message::create(['workspace_id' => $owner['workspace_id'], 'conversation_id' => $chat->id, 'direction' => 'inbound', 'type' => 'text', 'body' => 'book kar do', 'status' => 'received']);
    $message->forceFill(['created_at' => $customerWroteRecently ? now() : now()->subDays(3)])->save();

    Http::fake(fn () => Http::response(['messages' => [['id' => 'wamid.out'.uniqid()]]]));

    return [headersFor($owner['token'], $owner['workspace_id']), $owner['workspace_id'], $chat];
}

// A confirmed booking that starts $hours from now (negative = already started), in the business's own time
function bookingAt(int $workspace, Conversation $chat, float $hours, array $extra = []): Order
{
    $order = Order::create([
        'workspace_id' => $workspace,
        'conversation_id' => $chat->id,
        'customer_name' => 'Meena',
        'status' => 'confirmed',
        'total' => 500,
        'booking_at' => now('Asia/Kolkata')->addMinutes((int) ($hours * 60))->toDateTimeString(),
        'duration_minutes' => 60,
        ...$extra,
    ]);
    $order->items()->create(['name' => 'Haircut', 'price' => 500, 'quantity' => 1]);

    return $order;
}

test('a booking that is close gets a reminder suggested, once, and nothing is sent yet', function () {
    [$headers, $workspace, $chat] = followUpBusiness($this);
    bookingAt($workspace, $chat, 5);
    bookingAt($workspace, $chat, 30);                                  // too far away
    bookingAt($workspace, $chat, 6, ['status' => 'draft']);            // not confirmed
    bookingAt($workspace, $chat, 7, ['reminded_at' => now()]);         // already reminded

    Artisan::call('agents:propose-booking-followups');
    Artisan::call('agents:propose-booking-followups'); // the next hour: not suggested again

    $this->getJson('/api/actions', $headers)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'booking.reminder')
        ->assertJsonPath('data.0.agent', 'operations')
        ->assertJsonPath('data.0.conversation_id', $chat->id);

    expect(AgentAction::first()->summary)->toStartWith('Remind Meena about Haircut on ');
    Http::assertNothingSent();
});

test('when the owner says yes the customer gets the reminder and the booking is marked reminded', function () {
    [$headers, $workspace, $chat] = followUpBusiness($this);
    $order = bookingAt($workspace, $chat, 5);
    Artisan::call('agents:propose-booking-followups');
    $action = $this->getJson('/api/actions', $headers)->json('data.0.id');

    $this->putJson("/api/actions/{$action}", ['decision' => 'approve'], $headers)->assertJsonPath('data.status', 'executed');

    $sent = Message::where('conversation_id', $chat->id)->where('direction', 'outbound')->first();
    expect($sent->body)->toContain('Namaste Meena')->toContain('your Haircut is on')->and($order->refresh()->reminded_at)->not->toBeNull();
});

test('a reminder to a customer who has not written for a day is escalated, and the booking stays unmarked', function () {
    [$headers, $workspace, $chat] = followUpBusiness($this, customerWroteRecently: false);
    $order = bookingAt($workspace, $chat, 5);
    Artisan::call('agents:propose-booking-followups');
    $action = $this->getJson('/api/actions', $headers)->json('data.0.id');

    $this->putJson("/api/actions/{$action}", ['decision' => 'approve'], $headers)
        ->assertJsonPath('data.status', 'escalated')
        ->assertJsonPath('data.error', fn ($error) => str_contains($error, 'approved template'));

    expect($order->refresh()->reminded_at)->toBeNull()->and(Message::where('direction', 'outbound')->count())->toBe(0);
});

test('a booking cancelled after the reminder was suggested gets no message', function () {
    [$headers, $workspace, $chat] = followUpBusiness($this);
    $order = bookingAt($workspace, $chat, 5);
    Artisan::call('agents:propose-booking-followups');
    $order->update(['status' => 'cancelled']);
    $action = $this->getJson('/api/actions', $headers)->json('data.0.id');

    $this->putJson("/api/actions/{$action}", ['decision' => 'approve'], $headers)->assertJsonPath('data.status', 'escalated');

    expect(Message::where('direction', 'outbound')->count())->toBe(0);
});

test('with the standing yes on, reminders and feedback requests go out by themselves', function () {
    [$headers, $workspace, $chat] = followUpBusiness($this);
    Workspace::find($workspace)->update(['auto_booking_followups' => true]);
    bookingAt($workspace, $chat, 5);
    bookingAt($workspace, $chat, -4);

    Artisan::call('agents:propose-booking-followups');

    $this->getJson('/api/actions?status=executed', $headers)->assertJsonCount(2, 'data');
    expect(Message::where('conversation_id', $chat->id)->where('direction', 'outbound')->count())->toBe(2)
        ->and(AgentAction::whereNotNull('decided_by')->count())->toBe(0); // nobody clicked
});

test('feedback is asked a little after a booking ended, not before and not long after', function () {
    [$headers, $workspace, $chat] = followUpBusiness($this);
    bookingAt($workspace, $chat, -4);        // ended 3 hours ago: ask
    bookingAt($workspace, $chat, -1.5);      // ended 30 minutes ago: too soon
    bookingAt($workspace, $chat, -80);       // days ago: too late

    Artisan::call('agents:propose-booking-followups');

    $this->getJson('/api/actions', $headers)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'feedback.request')
        ->assertJsonPath('data.0.summary', 'Ask Meena for feedback on Haircut');
});

test('when the owner says yes the customer is asked for feedback', function () {
    [$headers, $workspace, $chat] = followUpBusiness($this);
    $order = bookingAt($workspace, $chat, -4);
    Artisan::call('agents:propose-booking-followups');
    $action = $this->getJson('/api/actions', $headers)->json('data.0.id');

    $this->putJson("/api/actions/{$action}", ['decision' => 'approve'], $headers)->assertJsonPath('data.status', 'executed');

    $sent = Message::where('conversation_id', $chat->id)->where('direction', 'outbound')->first();
    expect($sent->body)->toContain('How was your Haircut')->toContain('1 (poor) to 5 (excellent)')->and($order->refresh()->feedback_asked_at)->not->toBeNull();

    Artisan::call('agents:propose-booking-followups');
    $this->getJson('/api/actions', $headers)->assertJsonCount(0, 'data'); // asked once
});

test('the owner can switch the standing yes on and off', function () {
    [$headers] = followUpBusiness($this);

    $this->putJson('/api/workspace/profile', ['auto_booking_followups' => true], $headers)->assertOk()->assertJsonPath('data.auto_booking_followups', true);
    $this->putJson('/api/workspace/profile', ['auto_booking_followups' => false], $headers)->assertJsonPath('data.auto_booking_followups', false);
});

test('a reminder that WhatsApp refused is not marked as sent, so it can be tried again', function () {
    [$headers, $workspace, $chat] = followUpBusiness($this);
    $order = bookingAt($workspace, $chat, 5);
    Artisan::call('agents:propose-booking-followups');
    $action = $this->getJson('/api/actions', $headers)->json('data.0.id');
    Http::swap(new Factory);
    Http::fake(fn () => Http::response(['error' => ['message' => 'refused']], 400));

    $this->putJson("/api/actions/{$action}", ['decision' => 'approve'], $headers)->assertJsonPath('data.status', 'escalated');

    expect($order->refresh()->reminded_at)->toBeNull();
});

test('a shop whose trial is over gets no automatic follow-ups', function () {
    [$headers, $workspace, $chat] = followUpBusiness($this);
    Workspace::find($workspace)->update(['trial_ends_at' => now()->subDay(), 'auto_booking_followups' => true]);
    bookingAt($workspace, $chat, 5);

    Artisan::call('agents:propose-booking-followups');

    expect(AgentAction::count())->toBe(0)->and(Message::where('direction', 'outbound')->count())->toBe(0);
});
