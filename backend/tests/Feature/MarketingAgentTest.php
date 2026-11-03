<?php

use App\Models\Product;
use App\Services\Agents\Marketing\MarketerInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(RefreshDatabase::class);

// A pretend Marketing model: writes what it is told to and remembers what it was asked.
function fakeMarketer(?array $result): object
{
    $marketer = new class($result) implements MarketerInterface
    {
        public array $asked = [];

        public function __construct(private ?array $result) {}

        public function draft(string $business, array $products, string $brief): ?array
        {
            $this->asked[] = [$business, $products, $brief];

            return $this->result;
        }
    };

    app()->instance(MarketerInterface::class, $marketer);

    return $marketer;
}

function marketingOwner(TestCase $test): array
{
    $owner = registerOwner($test);

    return [headersFor($owner['token'], $owner['workspace_id']), $owner['workspace_id']];
}

test('the owner gets a draft written from the real products, ready for Meta, and nothing is sent or saved', function () {
    [$headers, $workspace] = marketingOwner($this);
    Product::create(['workspace_id' => $workspace, 'name' => 'Atta', 'price' => 250, 'unit' => '5 kg']);
    Product::create(['workspace_id' => $workspace, 'name' => 'Old stock', 'price' => 1, 'active' => false]);
    $marketer = fakeMarketer(['name' => 'sunday_special', 'language' => 'hi', 'body' => 'Namaste {{1}}! Atta Rs 250 per 5 kg. Order karein.']);

    $this->postJson('/api/campaign-drafts', ['brief' => 'Sunday grocery offer bhejna hai'], $headers)
        ->assertOk()
        ->assertJsonPath('data.name', 'offer_sunday_special')
        ->assertJsonPath('data.category', 'MARKETING')
        ->assertJsonPath('data.language', 'hi')
        ->assertJsonPath('data.examples', ['Ramesh'])
        ->assertJsonPath('data.body', "Namaste {{1}}! Atta Rs 250 per 5 kg. Order karein.\n\nBand karne ke liye STOP likhein.");

    expect($marketer->asked[0][0])->toBe('Acme owner@example.com')
        ->and($marketer->asked[0][1])->toBe([['name' => 'Atta', 'price' => '250.00', 'unit' => '5 kg']])
        ->and($marketer->asked[0][2])->toBe('Sunday grocery offer bhejna hai');
});

test('any other blank the model writes becomes the customer\'s name, and a way to stop is added', function () {
    [$headers] = marketingOwner($this);
    fakeMarketer(['name' => 'x', 'language' => 'en', 'body' => 'Big sale {{2}} today {{3}}!']);

    $body = $this->postJson('/api/campaign-drafts', ['brief' => 'big sale today'], $headers)->json('data.body');

    // we have no other value to fill a second blank with
    expect($body)->toStartWith('Big sale {{1}} today {{1}}!')->toEndWith('Reply STOP to opt out.');
});

test('a draft without the name blank gets a greeting with it', function () {
    [$headers] = marketingOwner($this);
    fakeMarketer(['name' => 'x', 'language' => 'en', 'body' => 'Big sale today!']);

    $body = $this->postJson('/api/campaign-drafts', ['brief' => 'big sale today'], $headers)->json('data.body');

    expect($body)->toStartWith("Namaste {{1}}!\n\nBig sale today!");
});

test('our way to stop is added even when the text has the word stop in it', function () {
    [$headers] = marketingOwner($this);
    fakeMarketer(['name' => 'x', 'language' => 'hi', 'body' => 'Namaste {{1}}! Non-stop savings on atta.']);

    $body = $this->postJson('/api/campaign-drafts', ['brief' => 'big sale today'], $headers)->json('data.body');

    expect($body)->toEndWith('Band karne ke liye STOP likhein.');
});

test('a long draft is never cut in the middle of a blank', function () {
    [$headers] = marketingOwner($this);
    fakeMarketer(['name' => 'x', 'language' => 'en', 'body' => str_repeat('a', 1024 - 24 - 7).' {{1}} tail']);

    $body = $this->postJson('/api/campaign-drafts', ['brief' => 'a long offer'], $headers)->json('data.body');

    expect($body)->not->toContain('{{1}} tail')->and(substr_count($body, '{{'))->toBe(substr_count($body, '}}'));
});

test('a draft never goes over Meta\'s length limit, even with the stop line', function () {
    [$headers] = marketingOwner($this);
    fakeMarketer(['name' => 'x', 'language' => 'en', 'body' => 'Namaste {{1}}! '.str_repeat('Offer ', 400)]);

    $body = $this->postJson('/api/campaign-drafts', ['brief' => 'a very long offer'], $headers)->json('data.body');

    expect(mb_strlen($body))->toBeLessThanOrEqual(1024)->and($body)->toEndWith('Reply STOP to opt out.');
});

test('no answer from the model says so, and a short brief is refused', function () {
    [$headers] = marketingOwner($this);
    fakeMarketer(null);

    $this->postJson('/api/campaign-drafts', ['brief' => 'Sunday offer please'], $headers)
        ->assertStatus(503)
        ->assertJsonPath('message', 'The message could not be written this time. Please try again.');

    $this->postJson('/api/campaign-drafts', ['brief' => 'hi'], $headers)->assertUnprocessable()->assertJsonValidationErrors('brief');
});
