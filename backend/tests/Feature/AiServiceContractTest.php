<?php

use App\Services\Agents\Finance\HttpPromiseDetector;
use App\Services\Agents\Marketing\HttpMarketer;
use App\Services\Agents\Operations\HttpOperations;
use App\Services\Agents\Sales\HttpQualifier;
use App\Services\Translation\HttpTranslator;
use Illuminate\Support\Facades\Http;

// What each adapter sends to the ai-service. The names must be the ones its endpoints expect
// (ai-service/tests/test_endpoints.py sends the same), or the call fails with a 422 / 500 on the live system.

function sentToAiService(string $path, array $answer, callable $call): array
{
    Http::fake(['*' => Http::response($answer)]);

    $result = $call();
    $sent = null;
    Http::assertSent(function ($request) use ($path, &$sent) {
        $sent = $request->data();

        return str_ends_with($request->url(), $path) && $request->method() === 'POST';
    });

    return [$sent, $result];
}

test('the translator sends text and target, and reads translation and language', function () {
    [$sent, $result] = sentToAiService('/translate', ['translation' => 'नमस्ते', 'language' => 'en'], fn () => app(HttpTranslator::class)->translate('Hello', 'Hindi'));

    expect(array_keys($sent))->toBe(['text', 'target'])->and($result)->toBe(['translation' => 'नमस्ते', 'language' => 'en']);
});

test('the Sales agent sends the questions and the chat', function () {
    $answer = ['answers' => [null], 'reply' => 'ok', 'done' => false, 'score' => 'warm', 'summary' => ''];
    [$sent, $result] = sentToAiService('/qualify', $answer, fn () => app(HttpQualifier::class)->qualify(['What do you need?'], 'Customer: hi'));

    expect($sent)->toBe(['questions' => ['What do you need?'], 'transcript' => 'Customer: hi'])->and($result)->toBe($answer);
});

test('the Finance agent sends the chat and today, and reads the list of promises', function () {
    $promise = ['kind' => 'payment', 'quote' => 'kal dunga', 'amount' => 500.5, 'due_on' => '2026-11-05'];
    [$sent, $result] = sentToAiService('/promises', ['promises' => [$promise]], fn () => app(HttpPromiseDetector::class)->detect('Customer: kal dunga', '2026-11-04'));

    expect($sent)->toBe(['transcript' => 'Customer: kal dunga', 'today' => '2026-11-04'])->and($result)->toBe([$promise]);
});

test('the Orders assistant sends products, chat, day, the address switch and the booking rules', function () {
    $products = [['id' => 1, 'name' => 'Haircut', 'price' => '200.00', 'unit' => null, 'stock' => null]];
    $answer = ['reply' => 'ok', 'items' => [], 'booking_at' => null, 'address' => null, 'ready' => false];
    [$sent, $result] = sentToAiService('/operations', $answer, fn () => app(HttpOperations::class)->respond($products, 'Customer: hi', '2026-11-04', true, 'Bookings: open 10:00 to 19:00'));

    expect($sent)->toBe([
        'products' => $products,
        'transcript' => 'Customer: hi',
        'today' => '2026-11-04',
        'needs_address' => true,
        'booking_rules' => 'Bookings: open 10:00 to 19:00',
    ])->and($result)->toBe($answer);
});

test('the Marketing agent sends the business, the products and the brief', function () {
    $products = [['name' => 'Atta', 'price' => '250.00', 'unit' => '5 kg']];
    $answer = ['name' => 'offer', 'language' => 'hi', 'body' => 'Namaste {{1}}!'];
    [$sent, $result] = sentToAiService('/campaign-draft', $answer, fn () => app(HttpMarketer::class)->draft('Ramesh Kirana', $products, 'Sunday offer'));

    expect($sent)->toBe(['business' => 'Ramesh Kirana', 'products' => $products, 'brief' => 'Sunday offer'])->and($result)->toBe($answer);
});

test('when the ai-service fails, every adapter answers null instead of throwing', function (callable $call) {
    Http::fake(['*' => Http::response(['detail' => 'boom'], 500)]);

    expect($call())->toBeNull();
})->with([
    'translator' => [fn () => app(HttpTranslator::class)->translate('Hello', 'Hindi')],
    'sales' => [fn () => app(HttpQualifier::class)->qualify(['q'], 't')],
    'finance' => [fn () => app(HttpPromiseDetector::class)->detect('t', '2026-11-04')],
    'orders' => [fn () => app(HttpOperations::class)->respond([], 't', '2026-11-04')],
    'marketing' => [fn () => app(HttpMarketer::class)->draft('Shop', [], 'offer')],
]);

test('the adapters call the address in the settings, not a fixed one', function () {
    config(['services.ai.url' => 'http://ai.example.test:9000/']);
    Http::fake(['*' => Http::response(['reply' => 'ok'])]);

    app(HttpOperations::class)->respond([], 't', '2026-11-04');

    Http::assertSent(fn ($request) => $request->url() === 'http://ai.example.test:9000/operations');
});
