<?php

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(RefreshDatabase::class);

function productOwner(TestCase $test, string $email = 'owner@example.com'): array
{
    $owner = registerOwner($test, $email);

    return [headersFor($owner['token'], $owner['workspace_id']), $owner['workspace_id']];
}

test('the owner adds, edits, lists and removes products', function () {
    [$headers] = productOwner($this);

    $id = $this->postJson('/api/products', ['name' => 'Solar lamp', 'price' => 450, 'unit' => 'piece', 'stock' => 12], $headers)
        ->assertCreated()
        ->assertJsonPath('data.name', 'Solar lamp')
        ->assertJsonPath('data.active', true)
        ->assertJsonPath('data.available', true)
        ->json('data.id');

    $this->putJson("/api/products/{$id}", ['stock' => 0], $headers)
        ->assertOk()
        ->assertJsonPath('data.stock', 0)
        ->assertJsonPath('data.available', false)
        ->assertJsonPath('data.name', 'Solar lamp');

    $this->getJson('/api/products', $headers)->assertJsonCount(1, 'data');
    $this->getJson('/api/products?search=lamp', $headers)->assertJsonCount(1, 'data');
    $this->getJson('/api/products?search=fan', $headers)->assertJsonCount(0, 'data');

    $this->deleteJson("/api/products/{$id}", [], $headers)->assertOk();
    $this->getJson('/api/products', $headers)->assertJsonCount(0, 'data');
});

test('a product without a stock number is always available, and an inactive one never', function () {
    [$headers] = productOwner($this);

    $this->postJson('/api/products', ['name' => 'Installation', 'price' => 500], $headers)
        ->assertJsonPath('data.stock', null)
        ->assertJsonPath('data.available', true);

    $this->postJson('/api/products', ['name' => 'Old lamp', 'price' => 100, 'stock' => 5, 'active' => false], $headers)
        ->assertJsonPath('data.available', false);
});

test('a product needs a name and a price that makes sense', function () {
    [$headers] = productOwner($this);

    $this->postJson('/api/products', ['name' => '', 'price' => -1, 'stock' => -3], $headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'price', 'stock']);
});

test('one business cannot see or change another business\'s products', function () {
    [$headers, $workspace] = productOwner($this);
    [$otherHeaders] = productOwner($this, 'two@example.com');
    $product = Product::create(['workspace_id' => $workspace, 'name' => 'Lamp', 'price' => 100]);

    $this->getJson('/api/products', $otherHeaders)->assertJsonCount(0, 'data');
    $this->putJson("/api/products/{$product->id}", ['price' => 1], $otherHeaders)->assertNotFound();
    $this->deleteJson("/api/products/{$product->id}", [], $otherHeaders)->assertNotFound();
});
