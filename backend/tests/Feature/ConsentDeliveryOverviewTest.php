<?php

use App\Enums\ActionType;
use App\Enums\AgentName;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\Order;
use App\Models\Product;
use App\Models\Workspace;
use App\Services\Actions\ActionService;
use App\Services\InboundMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(RefreshDatabase::class);

function restOwner(TestCase $test): array
{
    $owner = registerOwner($test);
    whatsappTestChannel($owner['workspace_id']);
    Http::fake(fn () => Http::response(['messages' => [['id' => 'wamid.out'.uniqid()]]]));

    return [headersFor($owner['token'], $owner['workspace_id']), $owner['workspace_id']];
}

function personWrites(string $text, string $from = '919876543210', string $name = 'Meena'): void
{
    app(InboundMessageService::class)->handle([
        'metadata' => ['phone_number_id' => '444000111'],
        'contacts' => [['profile' => ['name' => $name]]],
        'messages' => [['id' => 'wamid.'.uniqid(), 'from' => $from, 'type' => 'text', 'text' => ['body' => $text]]],
    ]);
}

// ---------- consent

test('permission given by hand is recorded with the date and where it came from', function () {
    [$headers] = restOwner($this);

    $this->postJson('/api/customers', ['name' => 'Ramesh', 'phone' => '919000000001', 'whatsapp_opt_in' => true], $headers)
        ->assertCreated()
        ->assertJsonPath('data.whatsapp_opt_in_source', 'manual')
        ->assertJsonPath('data.whatsapp_opt_in_at', fn ($at) => $at !== null);

    $this->postJson('/api/customers', ['name' => 'Suresh', 'phone' => '919000000002'], $headers)
        ->assertJsonPath('data.whatsapp_opt_in', false)
        ->assertJsonPath('data.whatsapp_opt_in_at', null);
});

test('taking the permission away clears the record', function () {
    [$headers, $workspace] = restOwner($this);
    $customer = Customer::create(['workspace_id' => $workspace, 'name' => 'Ramesh', 'phone' => '919000000001', 'whatsapp_opt_in' => true]);

    $this->putJson("/api/customers/{$customer->id}", ['whatsapp_opt_in' => false], $headers)
        ->assertOk()
        ->assertJsonPath('data.whatsapp_opt_in_at', null)
        ->assertJsonPath('data.whatsapp_opt_in_source', null);
});

test('a person who writes START is saved with permission, from the chat', function () {
    [, $workspace] = restOwner($this);

    personWrites('START');

    $customer = Customer::where('workspace_id', $workspace)->where('phone', '919876543210')->first();
    expect($customer->name)->toBe('Meena')
        ->and($customer->whatsapp_opt_in)->toBeTrue()
        ->and($customer->whatsapp_opt_in_source)->toBe('chat')
        ->and($customer->whatsapp_opt_in_at)->not->toBeNull();
});

test('STOP takes the permission away, and START brings it back', function () {
    [, $workspace] = restOwner($this);
    Customer::create(['workspace_id' => $workspace, 'name' => 'Meena', 'phone' => '919876543210', 'whatsapp_opt_in' => true, 'whatsapp_opt_in_source' => 'import']);

    personWrites('STOP');
    $customer = Customer::first();
    expect($customer->whatsapp_opt_in)->toBeFalse()->and($customer->opted_out_at)->not->toBeNull();

    personWrites('start');
    $customer->refresh();
    expect($customer->whatsapp_opt_in)->toBeTrue()->and($customer->opted_out_at)->toBeNull()->and($customer->whatsapp_opt_in_source)->toBe('chat');
});

test('an ordinary message is not a consent word', function () {
    restOwner($this);

    personWrites('Can you start the delivery today?');
    personWrites('yes');

    expect(Customer::count())->toBe(0);
});

// ---------- delivery update

function confirmedOrder(int $workspace, array $extra = []): Order
{
    $channel = Channel::where('workspace_id', $workspace)->first();
    $chat = Conversation::firstOrCreate(['workspace_id' => $workspace, 'channel_id' => $channel->id, 'customer_phone' => '919876543210'], ['customer_name' => 'Meena']);
    $message = Message::create(['workspace_id' => $workspace, 'conversation_id' => $chat->id, 'direction' => 'inbound', 'type' => 'text', 'body' => 'order', 'status' => 'received']);
    $message->forceFill(['created_at' => $extra['_wrote'] ?? now()])->save();
    unset($extra['_wrote']);

    Http::fake(fn () => Http::response(['messages' => [['id' => 'wamid.out'.uniqid()]]]));

    return Order::create(['workspace_id' => $workspace, 'conversation_id' => $chat->id, 'customer_name' => 'Meena', 'status' => 'confirmed', 'confirmed_at' => now(), 'total' => 900, ...$extra]);
}

test('the owner marks an order on the way, then delivered, and the customer is told each time', function () {
    [$headers, $workspace] = restOwner($this);
    $order = confirmedOrder($workspace);

    $this->putJson("/api/orders/{$order->id}", ['delivery_status' => 'on_the_way'], $headers)
        ->assertOk()
        ->assertJsonPath('message', 'Updated, and the customer was told.')
        ->assertJsonPath('data.delivery_status', 'on_the_way');

    $this->putJson("/api/orders/{$order->id}", ['delivery_status' => 'delivered'], $headers)->assertJsonPath('data.delivery_status', 'delivered');

    $told = Message::where('direction', 'outbound')->orderBy('id')->pluck('body')->all();
    expect($told)->toHaveCount(2)->and($told[0])->toContain('on the way')->and($told[1])->toContain('has been delivered');
});

test('when the customer cannot be told (a day passed) the mark is kept and the owner is told why', function () {
    [$headers, $workspace] = restOwner($this);
    $order = confirmedOrder($workspace, ['_wrote' => now()->subDays(3)]);

    $this->putJson("/api/orders/{$order->id}", ['delivery_status' => 'delivered'], $headers)
        ->assertOk()
        ->assertJsonPath('data.delivery_status', 'delivered')
        ->assertJsonPath('message', fn ($text) => str_contains($text, 'could not be told') && str_contains($text, 'approved template'));

    expect(Message::where('direction', 'outbound')->count())->toBe(0);
});

test('only a confirmed order of products can be sent for delivery, in your own business', function () {
    [$headers, $workspace] = restOwner($this);
    $draft = confirmedOrder($workspace, ['status' => 'draft']);
    $booking = confirmedOrder($workspace, ['booking_at' => now()->addDay()->toDateTimeString()]);
    $other = registerOwner($this, 'two@example.com');

    $this->putJson("/api/orders/{$draft->id}", ['delivery_status' => 'on_the_way'], $headers)->assertUnprocessable();
    $this->putJson("/api/orders/{$booking->id}", ['delivery_status' => 'on_the_way'], $headers)->assertUnprocessable();
    $this->putJson("/api/orders/{$draft->id}", ['delivery_status' => 'flying'], $headers)->assertUnprocessable()->assertJsonValidationErrors('delivery_status');
    $this->putJson("/api/orders/{$draft->id}", ['delivery_status' => 'delivered'], headersFor($other['token'], $other['workspace_id']))->assertNotFound();
});

// ---------- the Dashboard

test('the Dashboard numbers and workflows follow what the business has set up', function () {
    [$headers, $workspace] = restOwner($this);

    $empty = $this->getJson('/api/overview', $headers)->assertOk();
    expect(collect($empty->json('data.workflows'))->pluck('state', 'key')->all())
        ->toBe(['assistant' => 'active', 'orders' => 'off', 'leads' => 'off', 'campaign' => 'off']);

    $first = Customer::create(['workspace_id' => $workspace, 'name' => 'A', 'phone' => '919000000001']);
    $second = Customer::create(['workspace_id' => $workspace, 'name' => 'B', 'phone' => '919000000002']);
    Product::create(['workspace_id' => $workspace, 'name' => 'Haircut', 'price' => 200, 'duration_minutes' => 30]);
    Workspace::find($workspace)->update(['lead_flow_enabled' => true]);
    confirmedOrder($workspace);
    $campaign = Campaign::create(['workspace_id' => $workspace, 'channel_id' => 1, 'name' => 'Offer', 'template_name' => 'x', 'template_language' => 'en', 'template_body' => 'Hi', 'parameters' => [], 'status' => 'draft']);
    CampaignRecipient::create(['campaign_id' => $campaign->id, 'customer_id' => $first->id, 'phone' => '919000000001', 'parameters' => [], 'status' => 'sent']);
    CampaignRecipient::create(['campaign_id' => $campaign->id, 'customer_id' => $second->id, 'phone' => '919000000002', 'parameters' => [], 'status' => 'failed']);

    $data = $this->getJson('/api/overview', $headers)->json('data');

    expect($data['stats'])->toBe(['customers' => 2, 'campaign_messages' => 1, 'orders_this_month' => 1])
        ->and(collect($data['workflows'])->pluck('state', 'key')->all())
        ->toBe(['assistant' => 'active', 'orders' => 'active', 'follow_ups' => 'needs_ok', 'leads' => 'active', 'campaign' => 'needs_ok']);
});

test('when WhatsApp refuses the message the owner is not told that the customer knows', function () {
    [$headers, $workspace] = restOwner($this);
    $order = confirmedOrder($workspace);
    Http::swap(new Factory); // replaces the success answer set up with the order
    Http::fake(fn () => Http::response(['error' => ['message' => 'Invalid token']], 400));

    $this->putJson("/api/orders/{$order->id}", ['delivery_status' => 'on_the_way'], $headers)
        ->assertOk()
        ->assertJsonPath('data.delivery_status', 'on_the_way')
        ->assertJsonPath('message', fn ($text) => str_contains($text, 'could not be told'));
});

// ---------- paying and cancelling

test('the owner marks a confirmed order paid, and can take it back', function () {
    [$headers, $workspace] = restOwner($this);
    $order = confirmedOrder($workspace);

    $this->putJson("/api/orders/{$order->id}", ['paid' => true], $headers)->assertOk()->assertJsonPath('data.paid', true);
    $this->putJson("/api/orders/{$order->id}", ['paid' => false], $headers)->assertJsonPath('data.paid', false);

    $draft = confirmedOrder($workspace, ['status' => 'draft']);
    $this->putJson("/api/orders/{$draft->id}", ['paid' => true], $headers)->assertUnprocessable();
});

test('cancelling a confirmed order gives its stock back, and a cancelled one cannot be cancelled again', function () {
    [$headers, $workspace] = restOwner($this);
    $lamp = Product::create(['workspace_id' => $workspace, 'name' => 'Lamp', 'price' => 450, 'stock' => 3]);
    $service = Product::create(['workspace_id' => $workspace, 'name' => 'Installation', 'price' => 500]);
    $order = confirmedOrder($workspace);
    $order->items()->create(['product_id' => $lamp->id, 'name' => 'Lamp', 'price' => 450, 'quantity' => 2]);
    $order->items()->create(['product_id' => $service->id, 'name' => 'Installation', 'price' => 500, 'quantity' => 1]);

    $this->putJson("/api/orders/{$order->id}", ['status' => 'cancelled'], $headers)->assertOk()->assertJsonPath('data.status', 'cancelled');

    expect($lamp->refresh()->stock)->toBe(5)->and($service->refresh()->stock)->toBeNull();
    $this->putJson("/api/orders/{$order->id}", ['status' => 'cancelled'], $headers)->assertUnprocessable();
});

test('cancelling a draft takes nothing back, and nothing is sent without a change to make', function () {
    [$headers, $workspace] = restOwner($this);
    $lamp = Product::create(['workspace_id' => $workspace, 'name' => 'Lamp', 'price' => 450, 'stock' => 3]);
    $draft = confirmedOrder($workspace, ['status' => 'draft']);
    $draft->items()->create(['product_id' => $lamp->id, 'name' => 'Lamp', 'price' => 450, 'quantity' => 2]);

    $this->putJson("/api/orders/{$draft->id}", ['status' => 'cancelled'], $headers)->assertOk();
    expect($lamp->refresh()->stock)->toBe(3);

    $this->putJson("/api/orders/{$draft->id}", [], $headers)->assertUnprocessable();
});

test('the confirmation message tells how to pay by UPI when the business has a UPI id', function () {
    [$headers, $workspace] = restOwner($this);
    Workspace::find($workspace)->update(['upi_id' => 'ramesh@okaxis']);
    $order = confirmedOrder($workspace, ['status' => 'draft']);
    $order->items()->create(['name' => 'Lamp', 'price' => 450, 'quantity' => 2]);
    $action = app(ActionService::class)->propose($workspace, AgentName::Operations, ActionType::OrderConfirm, 'Order', ['order_id' => $order->id]);

    $this->putJson("/api/actions/{$action->id}", ['decision' => 'approve'], $headers)->assertJsonPath('data.status', 'executed');

    $told = Message::where('direction', 'outbound')->latest('id')->first()->body;
    expect($told)->toContain('Pay ₹900.00 on UPI to ramesh@okaxis')->toContain('upi://pay?pa=ramesh%40okaxis')->toContain('am=900.00');
});

test('STOP and START get a fixed answer, and the AI is not asked about them', function () {
    [, $workspace] = restOwner($this);
    Product::create(['workspace_id' => $workspace, 'name' => 'Lamp', 'price' => 450]);

    personWrites('START');
    personWrites('STOP');

    $told = Message::where('direction', 'outbound')->orderBy('id')->pluck('body')->all();
    expect($told)->toBe([
        'Thank you! You will now get our offers. Write STOP any time to stop them.',
        'Okay, you will not get offers from us any more. Write START any time to get them again.',
    ]);
});
