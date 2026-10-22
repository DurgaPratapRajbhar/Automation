<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
});

function customerPayload(array $overrides = []): array
{
    return [
        'name' => 'Ramesh Kumar',
        'phone' => '9876543210',
        'address' => 'Gali No. 3',
        'credit_limit' => 5000,
        'whatsapp_opt_in' => true,
        ...$overrides,
    ];
}

test('owner can create, list, show, update and delete a customer', function () {
    $owner = registerOwner($this);
    $headers = headersFor($owner['token'], $owner['workspace_id']);

    $created = $this->postJson('/api/customers', customerPayload(), $headers)
        ->assertCreated()
        ->assertJsonPath('data.name', 'Ramesh Kumar')
        ->assertJsonPath('data.phone', '9876543210')
        ->assertJsonPath('data.credit_limit', '5000.00')
        ->assertJsonPath('data.whatsapp_opt_in', true);

    $customerId = $created->json('data.id');

    $this->getJson('/api/customers', $headers)
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->getJson("/api/customers/{$customerId}", $headers)
        ->assertOk()
        ->assertJsonPath('data.address', 'Gali No. 3');

    $this->putJson("/api/customers/{$customerId}", [
        'name' => 'Ramesh K.',
        'credit_limit' => null,
    ], $headers)
        ->assertOk()
        ->assertJsonPath('data.name', 'Ramesh K.')
        ->assertJsonPath('data.credit_limit', null)
        ->assertJsonPath('data.phone', '9876543210');

    $this->deleteJson("/api/customers/{$customerId}", [], $headers)->assertOk();
    $this->getJson("/api/customers/{$customerId}", $headers)->assertNotFound();
});

test('search finds customers by name or phone, case-insensitive', function () {
    $owner = registerOwner($this);
    $headers = headersFor($owner['token'], $owner['workspace_id']);

    $this->postJson('/api/customers', customerPayload(), $headers)->assertCreated();
    $this->postJson('/api/customers', customerPayload(['name' => 'Geeta Devi', 'phone' => '9123456780']), $headers)->assertCreated();

    $this->getJson('/api/customers?search=ramesh', $headers)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Ramesh Kumar');

    $this->getJson('/api/customers?search=91234', $headers)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Geeta Devi');
});

test('same phone cannot be used twice in one workspace, but can in another', function () {
    $ownerA = registerOwner($this, 'a@example.com');
    $ownerB = registerOwner($this, 'b@example.com');
    $headersA = headersFor($ownerA['token'], $ownerA['workspace_id']);

    $this->postJson('/api/customers', customerPayload(), $headersA)->assertCreated();

    $this->postJson('/api/customers', customerPayload(['name' => 'Other']), $headersA)
        ->assertUnprocessable()
        ->assertJsonPath('message', 'A customer with this phone number already exists.');

    $this->postJson('/api/customers', customerPayload(), headersFor($ownerB['token'], $ownerB['workspace_id']))
        ->assertCreated();
});

test('updating to another customer phone is rejected', function () {
    $owner = registerOwner($this);
    $headers = headersFor($owner['token'], $owner['workspace_id']);

    $this->postJson('/api/customers', customerPayload(), $headers)->assertCreated();
    $secondId = $this->postJson('/api/customers', customerPayload(['name' => 'Geeta', 'phone' => '9123456780']), $headers)
        ->json('data.id');

    $this->putJson("/api/customers/{$secondId}", ['phone' => '9876543210'], $headers)
        ->assertUnprocessable();

    // Sending the customer's own phone again is fine
    $this->putJson("/api/customers/{$secondId}", ['phone' => '9123456780'], $headers)->assertOk();
});

test('gstin sets state code and must match a given state code', function () {
    $owner = registerOwner($this);
    $headers = headersFor($owner['token'], $owner['workspace_id']);

    $this->postJson('/api/customers', customerPayload(['gstin' => '09aaacr5055k1z5']), $headers)
        ->assertCreated()
        ->assertJsonPath('data.gstin', '09AAACR5055K1Z5')
        ->assertJsonPath('data.state_code', '09');

    $this->postJson('/api/customers', customerPayload([
        'phone' => '9000000001',
        'gstin' => '09AAACR5055K1Z5',
        'state_code' => '27',
    ]), $headers)
        ->assertUnprocessable()
        ->assertJsonPath('message', 'GSTIN does not match the state code.');
});

test('invalid input is rejected', function () {
    $owner = registerOwner($this);
    $headers = headersFor($owner['token'], $owner['workspace_id']);

    $this->postJson('/api/customers', ['phone' => '12ab'], $headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'phone']);

    $this->postJson('/api/customers', customerPayload(['gstin' => 'NOTAGSTIN', 'credit_limit' => -5]), $headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['gstin', 'credit_limit']);
});

test('customer permissions are enforced', function () {
    $owner = registerOwner($this);
    $ownerHeaders = headersFor($owner['token'], $owner['workspace_id']);

    $customerId = $this->postJson('/api/customers', customerPayload(), $ownerHeaders)->json('data.id');

    $viewer = memberWithPermissions($this, $owner, 'Customer Viewer', ['customers.view'], 'cv@example.com');
    $headers = headersFor($viewer['token'], $viewer['workspace_id']);

    $this->getJson('/api/customers', $headers)->assertOk();
    $this->getJson("/api/customers/{$customerId}", $headers)->assertOk();

    $this->postJson('/api/customers', customerPayload(['phone' => '9000000002']), $headers)->assertForbidden();
    $this->putJson("/api/customers/{$customerId}", ['name' => 'X'], $headers)->assertForbidden();
    $this->deleteJson("/api/customers/{$customerId}", [], $headers)->assertForbidden();
});

test('customers of another workspace are invisible', function () {
    $ownerA = registerOwner($this, 'a@example.com');
    $ownerB = registerOwner($this, 'b@example.com');

    $customerOfB = $this->postJson('/api/customers', customerPayload(), headersFor($ownerB['token'], $ownerB['workspace_id']))
        ->json('data.id');

    $headersA = headersFor($ownerA['token'], $ownerA['workspace_id']);

    $this->getJson('/api/customers', $headersA)->assertOk()->assertJsonCount(0, 'data');
    $this->getJson("/api/customers/{$customerOfB}", $headersA)->assertNotFound();
    $this->putJson("/api/customers/{$customerOfB}", ['name' => 'X'], $headersA)->assertNotFound();
    $this->deleteJson("/api/customers/{$customerOfB}", [], $headersA)->assertNotFound();
});
