<?php

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Order;
use App\Models\Product;
use App\Models\Workspace;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Services\Agents\Operations\OperationsInterface;
use App\Services\Agents\Sales\QualifierInterface;
use App\Services\Bookings\BookingSlotService;
use App\Services\InboundMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(RefreshDatabase::class);

// A pretend Orders model: says what it is told to say and remembers what it was asked.
function fakeOperations(?array $result): object
{
    $operations = new class($result) implements OperationsInterface
    {
        public array $asked = [];

        public function __construct(private ?array $result) {}

        public function respond(array $products, string $transcript, string $today, bool $needsAddress = false, ?string $bookingRules = null): ?array
        {
            $this->asked[] = [$products, $transcript, $today, $needsAddress, $bookingRules];

            return $this->result;
        }
    };

    app()->instance(OperationsInterface::class, $operations);

    return $operations;
}

function opsAnswer(string $reply, array $items = [], bool $ready = false, ?string $bookingAt = null, ?string $address = null): array
{
    return ['reply' => $reply, 'items' => $items, 'booking_at' => $bookingAt, 'address' => $address, 'ready' => $ready];
}

// A business that sells a counted product and a service, with a connected number
function opsBusiness(TestCase $test): array
{
    $owner = registerOwner($test);
    whatsappTestChannel($owner['workspace_id']);

    $lamp = Product::create(['workspace_id' => $owner['workspace_id'], 'name' => 'Solar lamp', 'price' => 450, 'unit' => 'piece', 'stock' => 5]);
    $install = Product::create(['workspace_id' => $owner['workspace_id'], 'name' => 'Installation', 'price' => 500]);

    // every WhatsApp send gets its own id
    Http::fake(fn () => Http::response(['messages' => [['id' => 'wamid.out'.uniqid()]]]));

    return [headersFor($owner['token'], $owner['workspace_id']), $owner['workspace_id'], $lamp, $install];
}

function customerAsks(string $text, string $from = '919876543210'): Conversation
{
    app(InboundMessageService::class)->handle([
        'metadata' => ['phone_number_id' => '444000111'],
        'contacts' => [['profile' => ['name' => 'Meena']]],
        'messages' => [['id' => 'wamid.'.uniqid(), 'from' => $from, 'type' => 'text', 'text' => ['body' => $text]]],
    ]);

    return Conversation::where('customer_phone', $from)->first();
}

test('a question about a product is answered from the product list', function () {
    opsBusiness($this);
    $operations = fakeOperations(opsAnswer('Solar lamp is ₹450 per piece, 5 available.'));

    $chat = customerAsks('Solar lamp ka rate kya hai?');

    expect($operations->asked)->toHaveCount(1)
        ->and(collect($operations->asked[0][0])->pluck('name')->all())->toBe(['Installation', 'Solar lamp'])
        ->and($operations->asked[0][1])->toBe('Customer: Solar lamp ka rate kya hai?');

    $sent = Message::where('conversation_id', $chat->id)->where('direction', 'outbound')->first();
    expect($sent->body)->toBe('Solar lamp is ₹450 per piece, 5 available.')->and(Order::count())->toBe(0);
});

test('a message that is not about buying does not reach this agent', function () {
    opsBusiness($this);
    $operations = fakeOperations(opsAnswer('x'));

    customerAsks('Good morning, how is your day');

    expect($operations->asked)->toBe([]);
});

test('a business with no products never uses this agent', function () {
    $owner = registerOwner($this);
    whatsappTestChannel($owner['workspace_id']);
    $operations = fakeOperations(opsAnswer('x'));

    customerAsks('Solar lamp ka rate kya hai?');

    expect($operations->asked)->toBe([]);
});

test('a ready order becomes a draft with OUR prices, and waits for the owner', function () {
    [$headers, $workspace, $lamp] = opsBusiness($this);
    fakeOperations(opsAnswer('Noted, 2 lamps.', [['product_id' => $lamp->id, 'quantity' => 2]], ready: true));

    $chat = customerAsks('2 solar lamp chahiye');

    $order = Order::with('items')->first();
    expect($order->status->value)->toBe('draft')
        ->and((float) $order->total)->toBe(900.0)
        ->and($order->customer_name)->toBe('Meena')
        ->and($order->items->first()->name)->toBe('Solar lamp')
        ->and($lamp->refresh()->stock)->toBe(5); // nothing is taken before the owner says yes

    $this->getJson('/api/actions', $headers)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'order.confirm')
        ->assertJsonPath('data.0.agent', 'operations')
        ->assertJsonPath('data.0.conversation_id', $chat->id)
        ->assertJsonPath('data.0.summary', 'Order: 2 × Solar lamp = ₹900.00 for Meena');
});

test('when the owner approves: stock is taken, the order is confirmed and the customer is told', function () {
    [$headers, , $lamp] = opsBusiness($this);
    fakeOperations(opsAnswer('Noted.', [['product_id' => $lamp->id, 'quantity' => 2]], ready: true));
    $chat = customerAsks('2 solar lamp chahiye');
    $action = $this->getJson('/api/actions', $headers)->json('data.0.id');

    $this->putJson("/api/actions/{$action}", ['decision' => 'approve'], $headers)
        ->assertOk()
        ->assertJsonPath('data.status', 'executed');

    expect(Order::first()->status->value)->toBe('confirmed')
        ->and($lamp->refresh()->stock)->toBe(3);

    $told = Message::where('conversation_id', $chat->id)->where('direction', 'outbound')->latest('id')->first();
    expect($told->body)->toContain('Your order is confirmed')->toContain('2 × Solar lamp')->toContain('Total: ₹900.00');
});

test('if the stock ran out before the owner said yes, nothing is taken and a person is told', function () {
    [$headers, , $lamp] = opsBusiness($this);
    fakeOperations(opsAnswer('Noted.', [['product_id' => $lamp->id, 'quantity' => 4]], ready: true));
    customerAsks('4 solar lamp chahiye');
    $lamp->update(['stock' => 1]); // sold to someone else meanwhile
    $action = $this->getJson('/api/actions', $headers)->json('data.0.id');

    $this->putJson("/api/actions/{$action}", ['decision' => 'approve'], $headers)
        ->assertOk()
        ->assertJsonPath('data.status', 'escalated')
        ->assertJsonPath('data.error', 'There is not enough stock of Solar lamp.');

    expect(Order::first()->status->value)->toBe('draft')->and($lamp->refresh()->stock)->toBe(1);
});

test('items the AI got wrong are dropped: an unknown product, an unavailable one, more than the stock', function () {
    [, , $lamp] = opsBusiness($this);
    fakeOperations(opsAnswer('Noted.', [['product_id' => 9999, 'quantity' => 1], ['product_id' => $lamp->id, 'quantity' => 50]], ready: true));

    customerAsks('50 solar lamp chahiye');

    expect(Order::count())->toBe(0);
});

test('a booking is an order with a time', function () {
    [$headers, , , $install] = opsBusiness($this);
    fakeOperations(opsAnswer('Noted.', [['product_id' => $install->id, 'quantity' => 1]], ready: true, bookingAt: '2026-11-07 17:00'));

    customerAsks('Installation book karna hai Saturday shaam 5 baje');

    $order = Order::first();
    expect($order->booking_at->format('Y-m-d H:i'))->toBe('2026-11-07 17:00');

    $this->getJson('/api/actions', $headers)->assertJsonPath('data.0.summary', 'Booking: 1 × Installation on 07 Nov, 05:00 PM = ₹500.00 for Meena');
});

test('the same chat does not get a second draft while one is waiting', function () {
    [, , $lamp] = opsBusiness($this);
    fakeOperations(opsAnswer('Noted.', [['product_id' => $lamp->id, 'quantity' => 1]], ready: true));

    customerAsks('1 solar lamp chahiye');
    customerAsks('haan confirm');

    expect(Order::count())->toBe(1);
});

test('no answer from the model: nothing is sent and nothing is drafted', function () {
    opsBusiness($this);
    fakeOperations(null);

    $chat = customerAsks('Solar lamp ka rate kya hai?');

    expect(Message::where('conversation_id', $chat->id)->where('direction', 'outbound')->count())->toBe(0)->and(Order::count())->toBe(0);
});

test('the Orders page lists orders with their items, newest first, by status', function () {
    [$headers, $workspace, $lamp] = opsBusiness($this);
    fakeOperations(opsAnswer('Noted.', [['product_id' => $lamp->id, 'quantity' => 2]], ready: true));
    customerAsks('2 solar lamp chahiye');
    Order::create(['workspace_id' => $workspace, 'status' => 'cancelled', 'confirmed_at' => null, 'total' => 10]);

    $this->getJson('/api/orders', $headers)->assertJsonCount(2, 'data')->assertJsonPath('data.0.status', 'cancelled');
    $this->getJson('/api/orders?status=draft', $headers)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.customer_name', 'Meena')
        ->assertJsonPath('data.0.total', '900.00')
        ->assertJsonPath('data.0.items.0.name', 'Solar lamp')
        ->assertJsonPath('data.0.items.0.quantity', 2);

    $other = registerOwner($this, 'two@example.com');
    $this->getJson('/api/orders', headersFor($other['token'], $other['workspace_id']))->assertJsonCount(0, 'data');
});

test('a business that delivers gets the address asked for, and an order without it is not drafted', function () {
    [$headers, $workspace, $lamp] = opsBusiness($this);
    Workspace::find($workspace)->update(['ask_delivery_address' => true]);

    $operations = fakeOperations(opsAnswer('Where should we deliver?', [['product_id' => $lamp->id, 'quantity' => 2]], ready: true));
    $chat = customerAsks('2 solar lamp chahiye');

    expect($operations->asked[0][3])->toBeTrue()->and(Order::count())->toBe(0);
});

test('with the address the order is drafted, shown to the owner, and told to the customer when confirmed', function () {
    [$headers, $workspace, $lamp] = opsBusiness($this);
    Workspace::find($workspace)->update(['ask_delivery_address' => true]);
    fakeOperations(opsAnswer('Noted.', [['product_id' => $lamp->id, 'quantity' => 2]], ready: true, address: '12 MG Road, Pune'));

    $chat = customerAsks('2 solar lamp chahiye, 12 MG Road, Pune');

    expect(Order::first()->address)->toBe('12 MG Road, Pune');

    $action = $this->getJson('/api/actions', $headers)
        ->assertJsonPath('data.0.summary', 'Order: 2 × Solar lamp = ₹900.00 for Meena, deliver to 12 MG Road, Pune')
        ->json('data.0.id');

    $this->putJson("/api/actions/{$action}", ['decision' => 'approve'], $headers)->assertJsonPath('data.status', 'executed');

    $told = Message::where('conversation_id', $chat->id)->where('direction', 'outbound')->latest('id')->first();
    expect($told->body)->toContain('Delivery to: 12 MG Road, Pune');
});

test('a business that does not deliver is never asked for an address, and a booking needs none', function () {
    [, , $lamp, $install] = opsBusiness($this);
    $operations = fakeOperations(opsAnswer('Noted.', [['product_id' => $install->id, 'quantity' => 1]], ready: true, bookingAt: '2026-11-07 17:00'));

    customerAsks('Installation book karna hai Saturday shaam 5 baje');

    expect($operations->asked[0][3])->toBeFalse()->and(Order::count())->toBe(1);
});

test('the owner gets a WhatsApp alert for a new order', function () {
    [, $workspace, $lamp] = opsBusiness($this);
    Workspace::find($workspace)->update(['phone' => '9000011111']);
    fakeOperations(opsAnswer('Noted.', [['product_id' => $lamp->id, 'quantity' => 2]], ready: true));

    customerAsks('2 solar lamp chahiye');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/messages')
        && $request['to'] === '919000011111'
        && str_contains($request['text']['body'], 'New order from Meena')
        && str_contains($request['text']['body'], '2 × Solar lamp')
        && str_contains($request['text']['body'], 'Total ₹900.00'));
});

test('no alert when the owner switched alerts off or saved no number, and the order is drafted anyway', function () {
    [, $workspace, $lamp] = opsBusiness($this);
    fakeOperations(opsAnswer('Noted.', [['product_id' => $lamp->id, 'quantity' => 1]], ready: true));

    customerAsks('1 solar lamp chahiye'); // no number saved

    Workspace::find($workspace)->update(['phone' => '9000011111', 'owner_alerts' => false]);
    customerAsks('1 solar lamp chahiye', '919876500000');

    expect(Order::count())->toBe(2);
    Http::assertNotSent(fn ($request) => ($request['to'] ?? null) === '919000011111');
});

test('when WhatsApp refuses the alert, the order is still drafted', function () {
    [, $workspace, $lamp] = opsBusiness($this);
    Workspace::find($workspace)->update(['phone' => '9000011111']);
    fakeOperations(opsAnswer('Noted.', [['product_id' => $lamp->id, 'quantity' => 1]], ready: true));
    Http::fake(fn ($request) => ($request['to'] ?? null) === '919000011111'
        ? Http::response(['error' => ['message' => 'Re-engagement message']], 400)
        : Http::response(['messages' => [['id' => 'wamid.out'.uniqid()]]]));

    customerAsks('1 solar lamp chahiye');

    expect(Order::count())->toBe(1);
});

// A time next week in the business's own time zone (so the tests do not depend on today's date)
function nextWeek(string $day, string $time): string
{
    return Carbon::parse("next {$day} {$time}", 'Asia/Kolkata')->addWeek()->format('Y-m-d H:i');
}

function bookable(int $workspace, int $minutes = 60): Product
{
    return Product::create(['workspace_id' => $workspace, 'name' => 'Consultation', 'price' => 300, 'duration_minutes' => $minutes]);
}

test('a time inside the hours is booked, and the booking remembers how long it takes', function () {
    [, $workspace] = opsBusiness($this);
    $service = bookable($workspace, 90);
    fakeOperations(opsAnswer('Booked.', [['product_id' => $service->id, 'quantity' => 1]], ready: true, bookingAt: nextWeek('tuesday', '11:00')));

    customerAsks('Consultation book karni hai Tuesday 11 baje');

    expect(Order::first()->duration_minutes)->toBe(90)->and(Order::count())->toBe(1);
});

test('a time that cannot work is never agreed: the customer is told why and offered free times', function (string $day, string $time, string $reason) {
    [, $workspace] = opsBusiness($this);
    Workspace::find($workspace)->update(['booking_closed_days' => ['sun']]);
    $service = bookable($workspace);
    fakeOperations(opsAnswer('Booked, see you!', [['product_id' => $service->id, 'quantity' => 1]], ready: true, bookingAt: nextWeek($day, $time)));

    $chat = customerAsks('Consultation book karni hai');

    $told = Message::where('conversation_id', $chat->id)->where('direction', 'outbound')->latest('id')->first()->body;
    expect($told)->not->toContain('see you')->toContain($reason)->toContain('Free times:')->and(Order::count())->toBe(0);
})->with([
    'a closed day' => ['sunday', '11:00', 'we are closed that day'],
    'before opening' => ['tuesday', '08:00', 'outside our working hours (10:00 to 19:00)'],
    'after closing' => ['tuesday', '22:00', 'outside our working hours'],
    'ends after closing' => ['tuesday', '18:30', 'outside our working hours'],
]);

test('a booked time is full, but a second chair makes room', function () {
    [, $workspace] = opsBusiness($this);
    $service = bookable($workspace);
    $at = nextWeek('wednesday', '12:00');

    fakeOperations(opsAnswer('Booked.', [['product_id' => $service->id, 'quantity' => 1]], ready: true, bookingAt: $at));
    customerAsks('Consultation Wednesday 12 baje');
    expect(Order::count())->toBe(1);

    // another person asks for the same time (capacity 1): not free
    $other = customerAsks('Consultation Wednesday 12 baje', '919000000002');
    $told = Message::where('conversation_id', $other->id)->where('direction', 'outbound')->latest('id')->first()->body;
    expect($told)->toContain('already booked')->and(Order::count())->toBe(1);

    // the business has two chairs now: the same time works for a second person
    Workspace::find($workspace)->update(['booking_capacity' => 2]);
    customerAsks('Consultation Wednesday 12 baje', '919000000003');
    expect(Order::count())->toBe(2);
});

test('a booking that overlaps part of another is full too', function () {
    [, $workspace] = opsBusiness($this);
    $service = bookable($workspace, 120);

    fakeOperations(opsAnswer('Booked.', [['product_id' => $service->id, 'quantity' => 1]], ready: true, bookingAt: nextWeek('thursday', '11:00')));
    customerAsks('Consultation Thursday 11 baje');

    fakeOperations(opsAnswer('Booked.', [['product_id' => $service->id, 'quantity' => 1]], ready: true, bookingAt: nextWeek('thursday', '12:00')));
    customerAsks('Consultation Thursday 12 baje', '919000000002');

    expect(Order::count())->toBe(1); // 11:00-13:00 is still running at 12:00
});

test('the assistant is told the booking rules only when the business has timed services', function () {
    [, $workspace] = opsBusiness($this);
    Workspace::find($workspace)->update(['booking_closed_days' => ['sun'], 'booking_capacity' => 2]);

    $operations = fakeOperations(opsAnswer('Noted.'));
    customerAsks('Solar lamp ka rate kya hai?');
    expect($operations->asked[0][4])->toBeNull(); // lamp and installation: no timed service

    bookable($workspace, 45);
    $operations = fakeOperations(opsAnswer('Noted.'));
    customerAsks('Consultation ka rate kya hai?', '919000000002');

    expect($operations->asked[0][4])->toContain('open 10:00 to 19:00')->toContain('closed on sun')->toContain('2 booking(s)')->toContain('about 60 minutes');
});

test('the owner can set the hours, the closed days and how many bookings fit at once', function () {
    [$headers] = opsBusiness($this);

    $this->putJson('/api/workspace/profile', ['booking_open_time' => '09:30', 'booking_close_time' => '21:00', 'booking_closed_days' => ['sun', 'mon'], 'booking_capacity' => 3], $headers)
        ->assertOk()
        ->assertJsonPath('data.booking_open_time', '09:30')
        ->assertJsonPath('data.booking_closed_days', ['sun', 'mon'])
        ->assertJsonPath('data.booking_capacity', 3);

    $this->putJson('/api/workspace/profile', ['booking_open_time' => '20:00', 'booking_close_time' => '10:00'], $headers)->assertUnprocessable()->assertJsonValidationErrors('booking_close_time');
    $this->putJson('/api/workspace/profile', ['booking_closed_days' => ['funday'], 'booking_capacity' => 0], $headers)->assertUnprocessable()->assertJsonValidationErrors(['booking_closed_days.0', 'booking_capacity']);
});

test('a product can have a length of time, for services booked at a time', function () {
    [$headers] = opsBusiness($this);

    $this->postJson('/api/products', ['name' => 'Haircut', 'price' => 200, 'duration_minutes' => 30], $headers)
        ->assertCreated()
        ->assertJsonPath('data.duration_minutes', 30);
    $this->postJson('/api/products', ['name' => 'Bad', 'price' => 1, 'duration_minutes' => 2], $headers)->assertUnprocessable()->assertJsonValidationErrors('duration_minutes');
});

test('a question that is not about buying stays with the support assistant, even with common words in it', function () {
    opsBusiness($this);
    $operations = fakeOperations(opsAnswer('x'));

    customerAsks('Aapki dukan kab khulti hai? Delivery hai kya?');

    expect($operations->asked)->toBe([]);
});

test('a short answer right after the orders assistant spoke stays with it', function () {
    [, , $lamp] = opsBusiness($this);
    $operations = fakeOperations(opsAnswer('How many do you need?'));
    customerAsks('Solar lamp ka rate kya hai?');

    customerAsks('haan');

    expect($operations->asked)->toHaveCount(2);
});

test('cancelling a draft order closes the question that waits for the owner', function () {
    [$headers, , $lamp] = opsBusiness($this);
    fakeOperations(opsAnswer('Noted.', [['product_id' => $lamp->id, 'quantity' => 1]], ready: true));
    customerAsks('1 solar lamp chahiye');

    $this->putJson('/api/orders/'.Order::first()->id, ['status' => 'cancelled'], $headers)->assertOk();

    $this->getJson('/api/actions', $headers)->assertJsonCount(0, 'data');
    $this->getJson('/api/actions?status=rejected', $headers)->assertJsonCount(1, 'data');
});

test('sales are counted by the day the order was confirmed, not the day it was last changed', function () {
    [$headers, $workspace] = opsBusiness($this);
    $yesterday = Order::create(['workspace_id' => $workspace, 'status' => 'confirmed', 'confirmed_at' => now()->subDay(), 'total' => 700]);
    $today = Order::create(['workspace_id' => $workspace, 'status' => 'confirmed', 'confirmed_at' => now(), 'total' => 300]);

    $this->putJson("/api/orders/{$yesterday->id}", ['paid' => true], $headers)->assertOk(); // changed today

    $sales = app(OrderRepositoryInterface::class)->confirmedBetween($workspace, now('Asia/Kolkata')->startOfDay(), now('Asia/Kolkata'));
    expect($sales['count'])->toBe(1)->and($sales['total'])->toBe(300.0);
});

test('only the closing time can be changed, and it must still be after the opening time', function () {
    [$headers] = opsBusiness($this);

    $this->putJson('/api/workspace/profile', ['booking_close_time' => '20:00'], $headers)->assertOk()->assertJsonPath('data.booking_close_time', '20:00');
    $this->putJson('/api/workspace/profile', ['booking_close_time' => '09:00'], $headers)->assertUnprocessable()->assertJsonValidationErrors('booking_close_time');
});

test('when the owner says no, the draft is cancelled and the same chat can order again', function () {
    [$headers, , $lamp] = opsBusiness($this);
    fakeOperations(opsAnswer('Noted.', [['product_id' => $lamp->id, 'quantity' => 1]], ready: true));
    customerAsks('1 solar lamp chahiye');
    $action = $this->getJson('/api/actions', $headers)->json('data.0.id');

    $this->putJson("/api/actions/{$action}", ['decision' => 'reject'], $headers)->assertOk();

    expect(Order::first()->status->value)->toBe('cancelled');

    customerAsks('1 solar lamp chahiye');
    expect(Order::where('status', 'draft')->count())->toBe(1);
});

test('dismissing a failed order confirmation cancels its draft', function () {
    [$headers, , $lamp] = opsBusiness($this);
    fakeOperations(opsAnswer('Noted.', [['product_id' => $lamp->id, 'quantity' => 4]], ready: true));
    customerAsks('4 solar lamp chahiye');
    $lamp->update(['stock' => 1]);
    $action = $this->getJson('/api/actions', $headers)->json('data.0.id');
    $this->putJson("/api/actions/{$action}", ['decision' => 'approve'], $headers)->assertJsonPath('data.status', 'escalated');

    $this->putJson("/api/actions/{$action}", ['decision' => 'reject'], $headers)->assertOk();

    expect(Order::first()->status->value)->toBe('cancelled');
});

test('an order whose chat is gone is not confirmed, and the stock stays', function () {
    [$headers, , $lamp] = opsBusiness($this);
    fakeOperations(opsAnswer('Noted.', [['product_id' => $lamp->id, 'quantity' => 2]], ready: true));
    $chat = customerAsks('2 solar lamp chahiye');
    $action = $this->getJson('/api/actions', $headers)->json('data.0.id');
    $chat->delete();

    $this->putJson("/api/actions/{$action}", ['decision' => 'approve'], $headers)->assertJsonPath('data.status', 'escalated');

    expect(Order::first()->status->value)->toBe('draft')->and($lamp->refresh()->stock)->toBe(5);
});

test('a shop with many products sends the assistant only the ones the customer talks about', function () {
    [, $workspace] = opsBusiness($this);
    config(['operations.max_products' => 5]);
    foreach (range(1, 12) as $number) {
        Product::create(['workspace_id' => $workspace, 'name' => "Item{$number}", 'price' => 10 + $number]);
    }
    Product::create(['workspace_id' => $workspace, 'name' => 'Basmati Rice', 'price' => 90]);
    $operations = fakeOperations(opsAnswer('Noted.'));

    customerAsks('Basmati rice ka rate kya hai?');

    $sent = collect($operations->asked[0][0])->pluck('name');
    expect($sent)->toHaveCount(1)->and($sent->first())->toBe('Basmati Rice');
});

test('a reply that shows a price that is not ours is not sent, and no order is drafted from it', function () {
    [, , $lamp] = opsBusiness($this);
    fakeOperations(opsAnswer('Solar lamp is Rs 400 only.', [['product_id' => $lamp->id, 'quantity' => 1]], ready: true));

    $chat = customerAsks('1 solar lamp chahiye');

    $told = Message::where('conversation_id', $chat->id)->where('direction', 'outbound')->latest('id')->first()->body;
    expect($told)->toBe('Let me check the exact price for you. The owner will tell you shortly.')->and(Order::count())->toBe(0);
});

test('our own prices, their multiples and the order total are allowed in a reply', function () {
    [, , $lamp] = opsBusiness($this);
    fakeOperations(opsAnswer('Solar lamp is ₹450 each, so 2 are Rs 900.00. Total: ₹1,350', [['product_id' => $lamp->id, 'quantity' => 3]], ready: true));

    $chat = customerAsks('3 solar lamp chahiye');

    $told = Message::where('conversation_id', $chat->id)->where('direction', 'outbound')->latest('id')->first()->body;
    expect($told)->toContain('Solar lamp is ₹450 each')->and(Order::count())->toBe(1);
});

test('one chat cannot make the bot answer without a stop', function () {
    opsBusiness($this);
    config(['operations.chat_messages_per_minute' => 2]);
    $operations = fakeOperations(opsAnswer('Noted.'));

    foreach (range(1, 5) as $ignored) {
        customerAsks('Solar lamp ka rate kya hai?');
    }

    expect($operations->asked)->toHaveCount(2)->and(Message::where('direction', 'inbound')->count())->toBe(5);
});

test('looking for free times reads the bookings once, however far it has to look', function () {
    [, $workspace] = opsBusiness($this);
    $workspace = Workspace::find($workspace);
    $workspace->update(['booking_closed_days' => ['sun', 'mon', 'tue', 'wed', 'thu', 'fri']]); // only Saturday is open
    DB::enableQueryLog();

    app(BookingSlotService::class)->nextFree($workspace, now('Asia/Kolkata'), 60);

    $reads = collect(DB::getQueryLog())->filter(fn ($query) => str_contains($query['query'], 'from "orders"') || str_contains($query['query'], 'from `orders`'))->count();
    expect($reads)->toBe(1);
});

test('changing a setting does not need the GST details to be complete', function () {
    [$headers, $workspace] = opsBusiness($this);
    Workspace::find($workspace)->update(['gst_status' => 'regular', 'gstin' => null]);

    $this->putJson('/api/workspace/profile', ['ask_delivery_address' => true], $headers)->assertOk()->assertJsonPath('data.ask_delivery_address', true);
    $this->putJson('/api/workspace/profile', ['upi_id' => 'shop@okaxis'], $headers)->assertOk();
    $this->putJson('/api/workspace/profile', ['gst_status' => 'regular'], $headers)->assertUnprocessable();
});

test('words that only end in rs or inr are not prices', function () {
    opsBusiness($this);
    fakeOperations(opsAnswer('We deliver in 2 hours. Our doctors 3 and orders 2 are ready. Solar lamp is Rs.450.'));

    $chat = customerAsks('Solar lamp ka rate kya hai?');

    $told = Message::where('conversation_id', $chat->id)->where('direction', 'outbound')->latest('id')->first()->body;
    expect($told)->toStartWith('We deliver in 2 hours');
});

test('a different order while one waits is drafted too, and the same one again is not', function () {
    [, , $lamp] = opsBusiness($this);

    fakeOperations(opsAnswer('Noted.', [['product_id' => $lamp->id, 'quantity' => 1]], ready: true));
    customerAsks('1 solar lamp chahiye');
    customerAsks('haan confirm');
    expect(Order::count())->toBe(1);

    fakeOperations(opsAnswer('Noted.', [['product_id' => $lamp->id, 'quantity' => 3]], ready: true));
    customerAsks('Actually 3 solar lamp chahiye');

    expect(Order::count())->toBe(2);
});

test('an absurd quantity is not drafted as an order', function () {
    [, , , $install] = opsBusiness($this);
    fakeOperations(opsAnswer('Noted.', [['product_id' => $install->id, 'quantity' => 5000]], ready: true));

    customerAsks('5000 installation chahiye');

    expect(Order::count())->toBe(0);
});

test('the booking time reaches the screen as the business\'s own clock time, not shifted', function () {
    [$headers, $workspace] = opsBusiness($this);
    Order::create(['workspace_id' => $workspace, 'status' => 'confirmed', 'total' => 500, 'booking_at' => '2026-11-07 17:00:00']);

    $this->getJson('/api/orders', $headers)->assertJsonPath('data.0.booking_at', '2026-11-07T17:00:00');
});

test('with the lead questions on, someone who writes about buying is answered, and a new lead is still asked', function () {
    [, $workspace] = opsBusiness($this);
    Workspace::find($workspace)->update(['lead_flow_enabled' => true, 'lead_questions' => ['What do you need?']]);
    $asked = new class implements QualifierInterface
    {
        public int $calls = 0;

        public function qualify(array $questions, string $transcript): ?array
        {
            $this->calls++;

            return ['answers' => [null], 'reply' => 'What do you need?', 'done' => false, 'score' => 'warm', 'summary' => ''];
        }
    };
    app()->instance(QualifierInterface::class, $asked);
    $operations = fakeOperations(opsAnswer('Solar lamp is Rs 450.'));

    customerAsks('Solar lamp ka rate kya hai?');                          // a buyer
    customerAsks('Hi, do you build websites for shops?', '919000000002');  // a new lead

    expect($operations->asked)->toHaveCount(1)->and($asked->calls)->toBe(1);
});

test('a question written in Hindi script reaches the orders assistant', function () {
    opsBusiness($this);
    $operations = fakeOperations(opsAnswer('Solar lamp Rs 450 ka hai.'));

    customerAsks('सोलर लैंप का रेट क्या है?');
    customerAsks('मुझे दो पीस चाहिए', '919000000002');

    expect($operations->asked)->toHaveCount(2);
});
