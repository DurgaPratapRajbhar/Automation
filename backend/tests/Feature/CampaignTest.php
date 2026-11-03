<?php

use App\Jobs\SendCampaignMessage;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Customer;
use App\Models\Message;
use App\Services\Campaigns\CampaignService;
use App\Services\InboundMessageService;
use App\Services\OutboundMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    config(['services.meta.graph_url' => 'https://graph.facebook.com', 'services.meta.graph_version' => 'v25.0']);
});

// A business with a connected number and four saved people:
// Ramesh and Sita agreed to WhatsApp, Anil did not, Mohan agreed but then asked us to stop.
function campaignBusiness(TestCase $test, string $email = 'owner@example.com'): array
{
    $owner = registerOwner($test, $email);
    $channel = whatsappTestChannel($owner['workspace_id']);
    $person = fn (string $name, string $phone, array $extra = []) => Customer::create([
        'workspace_id' => $owner['workspace_id'], 'name' => $name, 'phone' => $phone, 'whatsapp_opt_in' => true,
        'data' => ['Fees Due' => '1200', 'Last Date' => '10 Nov 2026'], ...$extra,
    ]);

    $people = [
        'ramesh' => $person('Ramesh', '919876543210'),
        'sita' => $person('Sita', '919123456789', ['data' => ['Fees Due' => '500', 'Last Date' => '12 Nov 2026']]),
        'anil' => $person('Anil', '919000000001', ['whatsapp_opt_in' => false]),
        'mohan' => $person('Mohan', '919000000002', ['opted_out_at' => now()]),
    ];

    return [headersFor($owner['token'], $owner['workspace_id']), $owner['workspace_id'], $channel, $people];
}

// Meta says the template is approved, and accepts every message (each gets its own wamid)
function fakeMetaForCampaigns(string $templateStatus = 'APPROVED', int $sendStatus = 200, ?string $limitTier = 'TIER_250'): void
{
    // A fresh fake each time: fakes added later would otherwise sit behind the earlier ones
    Http::swap(new HttpFactory);

    $sent = 0;

    Http::fake(function (Request $request) use ($templateStatus, $sendStatus, $limitTier, &$sent) {
        if (str_contains($request->url(), '/555666777/message_templates')) {
            return Http::response(['data' => [[
                'name' => 'fees_due', 'status' => $templateStatus, 'language' => 'en',
                'components' => [['type' => 'BODY', 'text' => 'Namaste {{1}}, fees {{2}} is due by {{3}}.']],
            ]]]);
        }

        if (str_contains($request->url(), '/444000111/messages')) {
            return $sendStatus === 200
                ? Http::response(['messages' => [['id' => 'wamid.c'.++$sent]]])
                : Http::response(['error' => ['message' => 'Re-engagement message', 'code' => 131047]], $sendStatus);
        }

        // The number's own details (its daily limit); $limitTier = null makes Meta fail
        if (str_contains($request->url(), '/444000111')) {
            return $limitTier ? Http::response(['messaging_limit_tier' => $limitTier, 'id' => '444000111']) : Http::response([], 500);
        }

        return Http::response([], 500);
    });
}

function campaignPayload(int $channelId, array $override = []): array
{
    return [
        'name' => 'Fees reminder',
        'channel_id' => $channelId,
        'template_name' => 'fees_due',
        'template_language' => 'en',
        'parameters' => [
            ['source' => 'name'],
            ['source' => 'column', 'value' => 'Fees Due'],
            ['source' => 'column', 'value' => 'Last Date'],
        ],
        ...$override,
    ];
}

test('a campaign picks only people who agreed and have not opted out, and shows how the first messages read', function () {
    [$headers, , $channel] = campaignBusiness($this);
    fakeMetaForCampaigns();

    $this->postJson('/api/campaigns', campaignPayload($channel->id), $headers)
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.report.total', 2)
        ->assertJsonPath('data.report.queued', 2)
        ->assertJsonPath('data.sample_messages.0.text', 'Namaste Ramesh, fees 1200 is due by 10 Nov 2026.')
        ->assertJsonPath('data.sample_messages.1.name', 'Sita')
        ->assertJsonPath('data.skipped.total', 2)
        ->assertJsonPath('data.skipped.reasons.no_permission', 1)
        ->assertJsonPath('data.skipped.reasons.opted_out', 1);

    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/messages'));
    expect(Message::count())->toBe(0);
});

test('a person with nothing for a blank is left out and named, because an empty value would be refused', function () {
    [$headers, , $channel, $people] = campaignBusiness($this);
    $people['sita']->update(['data' => ['Fees Due' => '500']]); // no Last Date
    fakeMetaForCampaigns();

    $this->postJson('/api/campaigns', campaignPayload($channel->id), $headers)
        ->assertCreated()
        ->assertJsonPath('data.report.total', 1)
        ->assertJsonPath('data.skipped.reasons.missing_value', 1)
        ->assertJsonPath('data.skipped.missing_values.0.name', 'Sita')
        ->assertJsonPath('data.skipped.missing_values.0.problem', 'Last Date is empty');
});

test('only the chosen people get it', function () {
    [$headers, , $channel, $people] = campaignBusiness($this);
    fakeMetaForCampaigns();

    $this->postJson('/api/campaigns', campaignPayload($channel->id, ['contact_ids' => [$people['sita']->id]]), $headers)
        ->assertCreated()
        ->assertJsonPath('data.report.total', 1)
        ->assertJsonPath('data.sample_messages.0.name', 'Sita');
});

test('a campaign is refused when the template is not approved, the blanks do not match, or nobody can get it', function () {
    [$headers, , $channel] = campaignBusiness($this);

    fakeMetaForCampaigns('PENDING');
    $this->postJson('/api/campaigns', campaignPayload($channel->id), $headers)
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Template fees_due is not approved by Meta yet.');

    fakeMetaForCampaigns();
    $this->postJson('/api/campaigns', campaignPayload($channel->id, ['parameters' => [['source' => 'name']]]), $headers)
        ->assertUnprocessable();

    Customer::query()->update(['whatsapp_opt_in' => false]);
    $this->postJson('/api/campaigns', campaignPayload($channel->id), $headers)
        ->assertUnprocessable()
        ->assertJsonPath('message', fn ($text) => str_contains($text, 'Nobody can get this message'));

    expect(Campaign::count())->toBe(0);
});

test('the request is checked: a source must be known and a column or text needs a value', function () {
    [$headers, , $channel] = campaignBusiness($this);

    $this->postJson('/api/campaigns', campaignPayload($channel->id, ['parameters' => [['source' => 'spaceship'], ['source' => 'column'], ['source' => 'text', 'value' => 'ok']]]), $headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['parameters.0.source', 'parameters.1.value']);
    $this->postJson('/api/campaigns', ['name' => 'x'], $headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['channel_id', 'template_name', 'template_language', 'parameters']);
});

test('starting queues one job per person and a campaign can only be started once', function () {
    [$headers, , $channel] = campaignBusiness($this);
    fakeMetaForCampaigns();
    $id = $this->postJson('/api/campaigns', campaignPayload($channel->id), $headers)->json('data.id');

    Queue::fake();

    $this->postJson("/api/campaigns/{$id}/start", [], $headers)
        ->assertOk()
        ->assertJsonPath('data.status', 'sending');
    Queue::assertPushed(SendCampaignMessage::class, 2);

    $this->postJson("/api/campaigns/{$id}/start", [], $headers)->assertUnprocessable();
    Queue::assertPushed(SendCampaignMessage::class, 2);
});

test('sending reaches every person: saved in the inbox with the blanks filled, sent by the one who started it', function () {
    [$headers, , $channel] = campaignBusiness($this);
    fakeMetaForCampaigns();
    $id = $this->postJson('/api/campaigns', campaignPayload($channel->id), $headers)->json('data.id');

    $this->postJson("/api/campaigns/{$id}/start", [], $headers)->assertOk();

    $this->getJson("/api/campaigns/{$id}", $headers)
        ->assertOk()
        ->assertJsonPath('data.status', 'done')
        ->assertJsonPath('data.report.sent', 2)
        ->assertJsonPath('data.report.queued', 0)
        ->assertJsonPath('data.report.failed', 0);

    $texts = Message::where('type', 'template')->orderBy('id')->pluck('body')->all();
    expect($texts)->toBe(['Namaste Ramesh, fees 1200 is due by 10 Nov 2026.', 'Namaste Sita, fees 500 is due by 12 Nov 2026.'])
        ->and(Message::where('type', 'template')->first()->sent_by_user_id)->not->toBeNull();

    Http::assertSent(fn (Request $r) => str_contains($r->url(), '/444000111/messages')
        && $r['to'] === '919876543210'
        && $r['template']['name'] === 'fees_due'
        && $r['template']['components'][0]['parameters'][1]['text'] === '1200');
    // Meta was asked about the template once for the whole campaign, not once per person
    expect(Http::recorded(fn (Request $r) => str_contains($r->url(), '/message_templates'))->count())->toBe(1);
});

test('the report follows delivered, read and replied as WhatsApp reports them', function () {
    [$headers, , $channel] = campaignBusiness($this);
    fakeMetaForCampaigns();
    $id = $this->postJson('/api/campaigns', campaignPayload($channel->id), $headers)->json('data.id');
    $this->postJson("/api/campaigns/{$id}/start", [], $headers)->assertOk();

    $outbound = app(OutboundMessageService::class);
    $outbound->applyStatuses(['statuses' => [['id' => 'wamid.c1', 'status' => 'delivered'], ['id' => 'wamid.c2', 'status' => 'delivered']]]);
    $outbound->applyStatuses(['statuses' => [['id' => 'wamid.c1', 'status' => 'read']]]);

    // Ramesh writes back
    app(InboundMessageService::class)->handle([
        'metadata' => ['phone_number_id' => '444000111'],
        'messages' => [['id' => 'wamid.reply1', 'from' => '919876543210', 'type' => 'text', 'text' => ['body' => 'kal de dunga']]],
    ]);

    $this->getJson("/api/campaigns/{$id}", $headers)
        ->assertJsonPath('data.report.total', 2)
        ->assertJsonPath('data.report.delivered', 2)
        ->assertJsonPath('data.report.read', 1)
        ->assertJsonPath('data.report.replied', 1);
});

test('a campaign that is stopped sends nothing more', function () {
    [$headers, , $channel] = campaignBusiness($this);
    fakeMetaForCampaigns();
    $id = $this->postJson('/api/campaigns', campaignPayload($channel->id), $headers)->json('data.id');

    Queue::fake();
    $this->postJson("/api/campaigns/{$id}/start", [], $headers)->assertOk();
    $this->postJson("/api/campaigns/{$id}/cancel", [], $headers)
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled')
        ->assertJsonPath('data.report.queued', 2);

    // The jobs that were already waiting in the queue now run, and do nothing
    CampaignRecipient::pluck('id')->each(fn ($recipientId) => app(CampaignService::class)->deliver($recipientId));

    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/444000111/messages'));
    expect(Message::count())->toBe(0);

    $this->postJson("/api/campaigns/{$id}/cancel", [], $headers)->assertUnprocessable();
});

test('a person who asks to stop after the campaign started is skipped', function () {
    [$headers, , $channel, $people] = campaignBusiness($this);
    fakeMetaForCampaigns();
    $id = $this->postJson('/api/campaigns', campaignPayload($channel->id), $headers)->json('data.id');

    Queue::fake();
    $this->postJson("/api/campaigns/{$id}/start", [], $headers)->assertOk();
    $people['ramesh']->update(['opted_out_at' => now()]);
    CampaignRecipient::pluck('id')->each(fn ($recipientId) => app(CampaignService::class)->deliver($recipientId));

    $this->getJson("/api/campaigns/{$id}", $headers)
        ->assertJsonPath('data.status', 'done')
        ->assertJsonPath('data.report.skipped', 1)
        ->assertJsonPath('data.report.sent', 1);
    expect(Message::where('type', 'template')->pluck('body')->all())->toBe(['Namaste Sita, fees 500 is due by 12 Nov 2026.']);
});

test('when WhatsApp refuses a message it is shown as failed with the reason, and the campaign still finishes', function () {
    [$headers, , $channel] = campaignBusiness($this);
    fakeMetaForCampaigns();
    $id = $this->postJson('/api/campaigns', campaignPayload($channel->id), $headers)->json('data.id');

    fakeMetaForCampaigns('APPROVED', 400);
    $this->postJson("/api/campaigns/{$id}/start", [], $headers)->assertOk();

    $this->getJson("/api/campaigns/{$id}", $headers)
        ->assertJsonPath('data.status', 'done')
        ->assertJsonPath('data.report.failed', 2)
        ->assertJsonPath('data.report.sent', 0);
    expect(CampaignRecipient::first()->error)->toContain('Re-engagement');
});

test('if the queue gives up on a person they show as failed instead of waiting for ever', function () {
    [$headers, , $channel] = campaignBusiness($this);
    fakeMetaForCampaigns();
    $id = $this->postJson('/api/campaigns', campaignPayload($channel->id), $headers)->json('data.id');

    Queue::fake();
    $this->postJson("/api/campaigns/{$id}/start", [], $headers)->assertOk();
    CampaignRecipient::pluck('id')->each(fn ($recipientId) => (new SendCampaignMessage($recipientId))->failed(new RuntimeException('worker crashed')));

    $this->getJson("/api/campaigns/{$id}", $headers)
        ->assertJsonPath('data.status', 'done')
        ->assertJsonPath('data.report.failed', 2);
});

test('the same message is never sent twice to a person, even if the job runs again', function () {
    [$headers, , $channel] = campaignBusiness($this);
    fakeMetaForCampaigns();
    $id = $this->postJson('/api/campaigns', campaignPayload($channel->id), $headers)->json('data.id');
    $this->postJson("/api/campaigns/{$id}/start", [], $headers)->assertOk();

    CampaignRecipient::pluck('id')->each(fn ($recipientId) => app(CampaignService::class)->deliver($recipientId));

    expect(Message::where('type', 'template')->count())->toBe(2);
});

test('a person writing STOP or BAND KARO is opted out of campaigns', function (string $text, bool $optedOut) {
    [, , , $people] = campaignBusiness($this);

    app(InboundMessageService::class)->handle([
        'metadata' => ['phone_number_id' => '444000111'],
        'messages' => [['id' => 'wamid.in'.uniqid(), 'from' => '919876543210', 'type' => 'text', 'text' => ['body' => $text]]],
    ]);

    $ramesh = $people['ramesh']->refresh();

    expect($ramesh->opted_out_at !== null)->toBe($optedOut)
        ->and($ramesh->whatsapp_opt_in)->toBe(! $optedOut);
})->with([
    ['STOP', true],
    ['  Stop!  ', true],
    ['Band karo.', true],
    ['बंद करो', true],
    ['Please stop the delivery at the gate', false],
    ['hello', false],
]);

test('campaigns of one business are invisible to another, and a number of another business cannot be used', function () {
    [$headers, , $channel] = campaignBusiness($this, 'one@example.com');
    fakeMetaForCampaigns();
    $id = $this->postJson('/api/campaigns', campaignPayload($channel->id), $headers)->json('data.id');

    $other = registerOwner($this, 'two@example.com');
    $otherHeaders = headersFor($other['token'], $other['workspace_id']);

    $this->getJson("/api/campaigns/{$id}", $otherHeaders)->assertNotFound();
    $this->postJson("/api/campaigns/{$id}/start", [], $otherHeaders)->assertNotFound();
    $this->postJson('/api/campaigns', campaignPayload($channel->id), $otherHeaders)->assertNotFound();
    $this->getJson('/api/campaigns', $otherHeaders)->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/api/campaigns', $headers)->assertOk()->assertJsonCount(1, 'data');
});

test('a business that signed up by itself cannot use campaigns until the super admin switches them on', function () {
    [$shop] = selfSignedUpShop($this);

    $this->getJson('/api/campaigns', $shop)->assertForbidden()->assertJsonPath('module', 'campaigns');
    $this->postJson('/api/campaigns', [], $shop)->assertForbidden();
});

test('the new-campaign page gets the connected numbers, the Excel columns and how many people can get one', function () {
    [$headers, , $channel] = campaignBusiness($this);

    $this->getJson('/api/campaigns/options', $headers)
        ->assertOk()
        ->assertJsonPath('data.channels.0.id', $channel->id)
        ->assertJsonPath('data.columns', ['Fees Due', 'Last Date'])
        ->assertJsonPath('data.people.ready', 2)
        ->assertJsonPath('data.people.total', 4);
});

test('a test message goes to the number the business writes, without touching the campaign', function () {
    [$headers, , $channel] = campaignBusiness($this);
    fakeMetaForCampaigns();
    $id = $this->postJson('/api/campaigns', campaignPayload($channel->id), $headers)->json('data.id');

    $this->postJson("/api/campaigns/{$id}/test", ['phone' => '+91 98111-22233'], $headers)
        ->assertOk()
        ->assertJsonPath('data.to', '919811122233')
        ->assertJsonPath('data.status', 'sent')
        ->assertJsonPath('data.text', 'Namaste Ramesh, fees 1200 is due by 10 Nov 2026.');

    Http::assertSent(fn (Request $r) => str_contains($r->url(), '/444000111/messages') && $r['to'] === '919811122233');
    $this->getJson("/api/campaigns/{$id}", $headers)
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.report.queued', 2)
        ->assertJsonPath('data.report.sent', 0);
    expect(Message::where('type', 'template')->count())->toBe(1);
});

test('a test needs a valid number and is only for a campaign that has not started', function () {
    [$headers, , $channel] = campaignBusiness($this);
    fakeMetaForCampaigns();
    $id = $this->postJson('/api/campaigns', campaignPayload($channel->id), $headers)->json('data.id');

    $this->postJson("/api/campaigns/{$id}/test", ['phone' => '12345'], $headers)->assertUnprocessable();
    $this->postJson("/api/campaigns/{$id}/test", [], $headers)->assertUnprocessable()->assertJsonValidationErrors('phone');

    $this->postJson("/api/campaigns/{$id}/start", [], $headers)->assertOk();
    $this->postJson("/api/campaigns/{$id}/test", ['phone' => '9811122233'], $headers)
        ->assertUnprocessable()
        ->assertJsonPath('message', 'A test can only be sent before the campaign starts.');
});

test('before sending, a campaign bigger than what WhatsApp allows the number per day is warned about', function () {
    [$headers, $workspaceId, $channel] = campaignBusiness($this);
    foreach (range(1, 60) as $number) {
        Customer::create(['workspace_id' => $workspaceId, 'name' => "Person {$number}", 'phone' => '9170000'.str_pad($number, 5, '0', STR_PAD_LEFT), 'whatsapp_opt_in' => true, 'data' => ['Fees Due' => '100', 'Last Date' => '1 Dec 2026']]);
    }

    fakeMetaForCampaigns(limitTier: 'TIER_50');
    $id = $this->postJson('/api/campaigns', campaignPayload($channel->id), $headers)
        ->assertCreated()
        ->assertJsonPath('data.sending_limit.tier', 'TIER_50')
        ->assertJsonPath('data.sending_limit.per_day', 50)
        ->assertJsonPath('data.sending_limit.over_limit', true)
        ->json('data.id');

    fakeMetaForCampaigns(limitTier: 'TIER_1K');
    $this->getJson("/api/campaigns/{$id}", $headers)
        ->assertJsonPath('data.sending_limit.per_day', 1000)
        ->assertJsonPath('data.sending_limit.over_limit', false);

    fakeMetaForCampaigns(limitTier: 'TIER_UNLIMITED');
    $this->getJson("/api/campaigns/{$id}", $headers)
        ->assertJsonPath('data.sending_limit.per_day', null)
        ->assertJsonPath('data.sending_limit.over_limit', false);
});

test('a number whose limit cannot be checked is not blocked, and the limit is only checked before the start', function () {
    [$headers, , $channel] = campaignBusiness($this);
    fakeMetaForCampaigns(limitTier: null);

    $id = $this->postJson('/api/campaigns', campaignPayload($channel->id), $headers)
        ->assertCreated()
        ->assertJsonPath('data.sending_limit', null)
        ->json('data.id');

    fakeMetaForCampaigns();
    $this->postJson("/api/campaigns/{$id}/start", [], $headers)->assertOk()->assertJsonPath('data.sending_limit', null);
});

test('the report names the people whose message did not go, with the reason', function () {
    [$headers, , $channel] = campaignBusiness($this);
    fakeMetaForCampaigns();
    $id = $this->postJson('/api/campaigns', campaignPayload($channel->id), $headers)->json('data.id');

    fakeMetaForCampaigns('APPROVED', 400);
    $this->postJson("/api/campaigns/{$id}/start", [], $headers)->assertOk();

    $this->getJson("/api/campaigns/{$id}", $headers)
        ->assertJsonPath('data.failed_people.0.name', 'Ramesh')
        ->assertJsonPath('data.failed_people.0.phone', '919876543210')
        ->assertJsonPath('data.failed_people.0.error', fn ($text) => str_contains($text, 'Re-engagement'))
        ->assertJsonCount(2, 'data.failed_people');
});

test('a message WhatsApp reports as failed later also shows with its reason', function () {
    [$headers, , $channel] = campaignBusiness($this);
    fakeMetaForCampaigns();
    $id = $this->postJson('/api/campaigns', campaignPayload($channel->id), $headers)->json('data.id');
    $this->postJson("/api/campaigns/{$id}/start", [], $headers)->assertOk();

    app(OutboundMessageService::class)->applyStatuses(['statuses' => [[
        'id' => 'wamid.c2', 'status' => 'failed', 'errors' => [['code' => 131026, 'title' => 'Message undeliverable']],
    ]]]);

    $this->getJson("/api/campaigns/{$id}", $headers)
        ->assertJsonPath('data.report.failed', 1)
        ->assertJsonPath('data.failed_people.0.name', 'Sita')
        ->assertJsonPath('data.failed_people.0.error', fn ($text) => str_contains($text, 'undeliverable'));
});
