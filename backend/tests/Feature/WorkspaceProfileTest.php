<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
});

test('owner can view and update the shop profile', function () {
    $owner = registerOwner($this);
    $headers = headersFor($owner['token'], $owner['workspace_id']);

    $this->getJson('/api/workspace/profile', $headers)
        ->assertOk()
        ->assertJsonPath('data.gst_status', 'unregistered')
        ->assertJsonPath('data.upi_id', null);

    $this->putJson('/api/workspace/profile', [
        'name' => 'Sharma Kirana',
        'phone' => '9876543210',
        'address' => 'Main Road',
        'upi_id' => 'sharma.store@okaxis',
        'gst_status' => 'regular',
        'gstin' => '09aaacr5055k1z5',
    ], $headers)
        ->assertOk()
        ->assertJsonPath('data.name', 'Sharma Kirana')
        ->assertJsonPath('data.gstin', '09AAACR5055K1Z5')
        ->assertJsonPath('data.state_code', '09')
        ->assertJsonPath('data.upi_id', 'sharma.store@okaxis');

    // Partial update keeps the other fields
    $this->putJson('/api/workspace/profile', ['address' => 'New Road'], $headers)
        ->assertOk()
        ->assertJsonPath('data.address', 'New Road')
        ->assertJsonPath('data.gstin', '09AAACR5055K1Z5');
});

test('gst registered workspace needs a gstin that matches the state', function () {
    $owner = registerOwner($this);
    $headers = headersFor($owner['token'], $owner['workspace_id']);

    $this->putJson('/api/workspace/profile', ['gst_status' => 'composition'], $headers)
        ->assertUnprocessable()
        ->assertJsonPath('message', 'GSTIN is required for a GST-registered workspace.');

    $this->putJson('/api/workspace/profile', ['gstin' => '09AAACR5055K1Z5', 'state_code' => '27'], $headers)
        ->assertUnprocessable()
        ->assertJsonPath('message', 'GSTIN does not match the state code.');
});

test('invalid profile input is rejected', function () {
    $owner = registerOwner($this);

    $this->putJson('/api/workspace/profile', [
        'phone' => '12',
        'upi_id' => 'not-a-upi',
        'gst_status' => 'maybe',
    ], headersFor($owner['token'], $owner['workspace_id']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['phone', 'upi_id', 'gst_status']);
});

test('profile permissions are enforced', function () {
    $owner = registerOwner($this);
    $viewer = memberWithPermissions($this, $owner, 'Profile Viewer', ['workspace.view'], 'pv@example.com');
    $headers = headersFor($viewer['token'], $viewer['workspace_id']);

    $this->getJson('/api/workspace/profile', $headers)->assertOk();
    $this->putJson('/api/workspace/profile', ['address' => 'X'], $headers)->assertForbidden();
});

test('each workspace sees only its own profile', function () {
    $ownerA = registerOwner($this, 'a@example.com');
    $ownerB = registerOwner($this, 'b@example.com');

    $this->putJson('/api/workspace/profile', ['address' => 'Shop B'], headersFor($ownerB['token'], $ownerB['workspace_id']))
        ->assertOk();

    $this->getJson('/api/workspace/profile', headersFor($ownerA['token'], $ownerA['workspace_id']))
        ->assertOk()
        ->assertJsonPath('data.address', null);

    $this->putJson('/api/workspace/profile', ['address' => 'X'], headersFor($ownerA['token'], $ownerB['workspace_id']))
        ->assertForbidden();
});
