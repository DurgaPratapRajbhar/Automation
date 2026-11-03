<?php

use App\Models\Conversation;
use App\Models\Promise;
use App\Models\Workspace;
use App\Services\Agents\Finance\PromiseDetectorInterface;
use App\Services\Agents\FinanceAgent;
use App\Services\AiUsageService;
use App\Services\InboundMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(RefreshDatabase::class);

// A pretend Finance model: finds the promises it is told to and remembers what it was asked.
function fakeDetector(?array $promises): object
{
    $detector = new class($promises) implements PromiseDetectorInterface
    {
        public array $asked = [];

        public function __construct(private ?array $promises) {}

        public function detect(string $transcript, string $today): ?array
        {
            $this->asked[] = [$transcript, $today];

            return $this->promises;
        }
    };

    app()->instance(PromiseDetectorInterface::class, $detector);

    return $detector;
}

function financeBusiness(TestCase $test): int
{
    $owner = registerOwner($test);
    whatsappTestChannel($owner['workspace_id']);

    return $owner['workspace_id'];
}

function customerSays(string $text, string $from = '919876543210'): void
{
    app(InboundMessageService::class)->handle([
        'metadata' => ['phone_number_id' => '444000111'],
        'contacts' => [['profile' => ['name' => 'Aarav']]],
        'messages' => [['id' => 'wamid.'.uniqid(), 'from' => $from, 'type' => 'text', 'text' => ['body' => $text]]],
    ]);
}

function aPayment(string $due = '2026-11-05'): array
{
    return ['kind' => 'payment', 'quote' => 'kal payment kar dunga', 'amount' => 1200.0, 'due_on' => $due];
}

test('a dated promise in a customer message is saved with the customer and the date', function () {
    $workspace = financeBusiness($this);
    $detector = fakeDetector([aPayment()]);

    customerSays('Kal payment kar dunga');

    expect($detector->asked)->toHaveCount(1)
        ->and($detector->asked[0][0])->toBe('Customer: Kal payment kar dunga')
        ->and($detector->asked[0][1])->toBe(now('Asia/Kolkata')->toDateString());

    $promise = Promise::first();
    expect($promise->workspace_id)->toBe($workspace)
        ->and($promise->customer_name)->toBe('Aarav')
        ->and($promise->customer_phone)->toBe('919876543210')
        ->and($promise->kind->value)->toBe('payment')
        ->and($promise->status->value)->toBe('open')
        ->and($promise->source->value)->toBe('agent')
        ->and($promise->due_on->toDateString())->toBe('2026-11-05')
        ->and((float) $promise->amount)->toBe(1200.0);
});

test('a message that cannot hold a promise does not cost an AI call', function () {
    financeBusiness($this);
    $detector = fakeDetector([aPayment()]);

    customerSays('Thanks, nice shop');
    customerSays('Hello');

    expect($detector->asked)->toBe([])->and(Promise::count())->toBe(0);
});

test('the same promise repeated is saved once', function () {
    financeBusiness($this);
    fakeDetector([aPayment()]);

    customerSays('Kal payment kar dunga');
    customerSays('Haan kal pakka payment');

    expect(Promise::count())->toBe(1);

    fakeDetector([aPayment('2026-11-09')]); // a new date is a new promise
    customerSays('Sorry, Monday ko payment');

    expect(Promise::count())->toBe(2);
});

test('no promise, or no answer from the model, saves nothing', function () {
    financeBusiness($this);

    fakeDetector([]);
    customerSays('Kal aaunga');
    fakeDetector(null);
    customerSays('Kal aaunga phir');

    expect(Promise::count())->toBe(0);
});

test('the business\'s own messages are not read for promises', function () {
    financeBusiness($this);
    $detector = fakeDetector([aPayment()]);

    customerSays('Hello'); // opens the chat
    $chat = Conversation::first();
    $message = $chat->messages()->create(['workspace_id' => $chat->workspace_id, 'direction' => 'outbound', 'type' => 'text', 'body' => 'Please pay by 10 Nov', 'status' => 'sent']);

    app(FinanceAgent::class)->observe($message->id);

    expect($detector->asked)->toBe([]);
});

test('the Finance agent only checks the daily allowance, so it does not use up the answers of the support bot', function () {
    $workspace = financeBusiness($this);
    Workspace::find($workspace)->update(['trial_ends_at' => now()->addDays(3)]);
    config(['trial.ai_daily_limit' => 1]);
    $usage = app(AiUsageService::class);

    expect($usage->allowed($workspace, counts: false))->toBeTrue()
        ->and($usage->allowed($workspace, counts: false))->toBeTrue()
        ->and($usage->allowed($workspace))->toBeTrue()   // the one answer of the day
        ->and($usage->allowed($workspace))->toBeFalse()
        ->and($usage->allowed($workspace, counts: false))->toBeFalse();
});

test('a promise dated in the past is the model\'s mistake and is not saved', function () {
    financeBusiness($this);
    fakeDetector([aPayment(now('Asia/Kolkata')->subDays(3)->toDateString()), aPayment(now('Asia/Kolkata')->addDay()->toDateString())]);

    customerSays('Kal payment kar dunga');

    expect(Promise::count())->toBe(1)->and(Promise::first()->due_on->toDateString())->toBe(now('Asia/Kolkata')->addDay()->toDateString());
});

test('a promise written in Hindi script, with no digit, is looked at', function () {
    financeBusiness($this);
    $detector = fakeDetector([aPayment()]);

    customerSays('मैं कल पैसे दे दूँगा');

    expect($detector->asked)->toHaveCount(1)->and(Promise::count())->toBe(1);
});
