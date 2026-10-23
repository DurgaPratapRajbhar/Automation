<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    Storage::fake('local');
    // Koi real ai-service call na jaye (indexer waise bhi Null hai testing me)
    Http::preventStrayRequests();
});

test('owner can create, list, show, update and delete a text knowledge source', function () {
    $owner = registerOwner($this);
    $headers = headersFor($owner['token'], $owner['workspace_id']);

    $created = $this->postJson('/api/knowledge-sources', [
        'type' => 'text',
        'title' => 'About the shop',
        'content' => 'We sell rice, wheat and spices. Open 9am-9pm.',
    ], $headers)
        ->assertCreated()
        ->assertJsonPath('data.type', 'text')
        ->assertJsonPath('data.title', 'About the shop')
        ->assertJsonPath('data.status', 'pending');

    $id = $created->json('data.id');

    $this->getJson('/api/knowledge-sources', $headers)->assertOk()->assertJsonCount(1, 'data');
    $this->getJson("/api/knowledge-sources/{$id}", $headers)
        ->assertOk()
        ->assertJsonPath('data.content', 'We sell rice, wheat and spices. Open 9am-9pm.');

    $this->putJson("/api/knowledge-sources/{$id}", ['title' => 'Shop info', 'content' => 'Updated info.'], $headers)
        ->assertOk()
        ->assertJsonPath('data.title', 'Shop info')
        ->assertJsonPath('data.content', 'Updated info.')
        ->assertJsonPath('data.status', 'pending');

    $this->deleteJson("/api/knowledge-sources/{$id}", [], $headers)->assertOk();
    $this->getJson("/api/knowledge-sources/{$id}", $headers)->assertNotFound();
});

test('owner can upload a file source and deleting it removes the file', function () {
    $owner = registerOwner($this);
    $headers = headersFor($owner['token'], $owner['workspace_id']);

    $created = $this->post('/api/knowledge-sources', [
        'type' => 'file',
        'title' => 'Price list',
        'file' => UploadedFile::fake()->create('prices.pdf', 120, 'application/pdf'),
    ], $headers)
        ->assertCreated()
        ->assertJsonPath('data.type', 'file')
        ->assertJsonPath('data.file_name', 'prices.pdf');

    $id = $created->json('data.id');
    $path = App\Models\KnowledgeSource::find($id)->file_path;
    Storage::disk('local')->assertExists($path);

    $this->deleteJson("/api/knowledge-sources/{$id}", [], $headers)->assertOk();
    Storage::disk('local')->assertMissing($path);
});

test('content can only be edited on a text source', function () {
    $owner = registerOwner($this);
    $headers = headersFor($owner['token'], $owner['workspace_id']);

    $id = $this->post('/api/knowledge-sources', [
        'type' => 'file',
        'title' => 'Menu',
        'file' => UploadedFile::fake()->create('menu.pdf', 50, 'application/pdf'),
    ], $headers)->json('data.id');

    $this->putJson("/api/knowledge-sources/{$id}", ['content' => 'hack'], $headers)
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Only text sources can have their content edited.');
});

test('validation rejects bad input', function () {
    $owner = registerOwner($this);
    $headers = headersFor($owner['token'], $owner['workspace_id']);

    $this->postJson('/api/knowledge-sources', [], $headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type', 'title']);

    $this->postJson('/api/knowledge-sources', ['type' => 'text', 'title' => 'x'], $headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['content']);

    $this->postJson('/api/knowledge-sources', ['type' => 'file', 'title' => 'x'], $headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file']);
});

test('knowledge permissions are enforced', function () {
    $owner = registerOwner($this);
    $ownerHeaders = headersFor($owner['token'], $owner['workspace_id']);

    $id = $this->postJson('/api/knowledge-sources', [
        'type' => 'text', 'title' => 'T', 'content' => 'hello world',
    ], $ownerHeaders)->json('data.id');

    $viewer = memberWithPermissions($this, $owner, 'Knowledge Viewer', ['knowledge-sources.view'], 'kv@example.com');
    $headers = headersFor($viewer['token'], $viewer['workspace_id']);

    $this->getJson('/api/knowledge-sources', $headers)->assertOk();
    $this->getJson("/api/knowledge-sources/{$id}", $headers)->assertOk();

    $this->postJson('/api/knowledge-sources', ['type' => 'text', 'title' => 'T2', 'content' => 'x'], $headers)->assertForbidden();
    $this->putJson("/api/knowledge-sources/{$id}", ['title' => 'X'], $headers)->assertForbidden();
    $this->deleteJson("/api/knowledge-sources/{$id}", [], $headers)->assertForbidden();
});

test('knowledge of another workspace is invisible', function () {
    $ownerA = registerOwner($this, 'a@example.com');
    $ownerB = registerOwner($this, 'b@example.com');

    $idB = $this->postJson('/api/knowledge-sources', [
        'type' => 'text', 'title' => 'B secret', 'content' => 'only B',
    ], headersFor($ownerB['token'], $ownerB['workspace_id']))->json('data.id');

    $headersA = headersFor($ownerA['token'], $ownerA['workspace_id']);

    $this->getJson('/api/knowledge-sources', $headersA)->assertOk()->assertJsonCount(0, 'data');
    $this->getJson("/api/knowledge-sources/{$idB}", $headersA)->assertNotFound();
    $this->putJson("/api/knowledge-sources/{$idB}", ['title' => 'X'], $headersA)->assertNotFound();
    $this->deleteJson("/api/knowledge-sources/{$idB}", [], $headersA)->assertNotFound();
});
